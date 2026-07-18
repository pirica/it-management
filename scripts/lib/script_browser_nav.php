<?php
/**
 * Shared navigation and outbound links for browser-run scripts under scripts/.
 *
 * Why: Every HTML report links back to the scripts catalog. Module/table names link to
 * modules/ only when a folder exists; phpMyAdmin is linked from scripts/scripts.php only.
 */

if (!function_exists('itm_script_is_cli_sapi')) {
    function itm_script_is_cli_sapi(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('itm_script_repo_root_path')) {
    function itm_script_repo_root_path(): string
    {
        if (defined('ROOT_PATH')) {
            return (string)ROOT_PATH;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_script_browser_nav_html')) {
    /**
     * @param string $baseUrl Unused; kept for callers. Nav always uses relative scripts/scripts.php.
     */
    function itm_script_browser_nav_html($baseUrl = ''): string
    {
        $indexEsc = htmlspecialchars('scripts.php', ENT_QUOTES, 'UTF-8');

        return '<nav class="itm-script-nav" aria-label="Scripts navigation" style="margin:0 0 16px;padding:10px 14px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:0.9rem;">'
            . '<a href="' . $indexEsc . '" style="color:#0969da;text-decoration:none;font-weight:600;">← Scripts index</a>'
            . '</nav>';
    }

    function itm_script_browser_nav_echo($baseUrl = ''): void
    {
        echo itm_script_browser_nav_html($baseUrl);
    }
}

if (!function_exists('itm_script_module_path_from_table')) {
    function itm_script_module_path_from_table($tableName): string
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return '';
        }

        return 'modules/' . $tableName . '/';
    }
}

if (!function_exists('itm_script_table_has_module')) {
    function itm_script_table_has_module($tableName): bool
    {
        static $cache = [];
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return false;
        }
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $root = itm_script_repo_root_path();
        $cache[$tableName] = is_file($root . 'modules' . DIRECTORY_SEPARATOR . $tableName . DIRECTORY_SEPARATOR . 'index.php');

        return $cache[$tableName];
    }
}

if (!function_exists('itm_script_module_relative_href')) {
    function itm_script_module_relative_href($moduleName, $page = 'index.php'): string
    {
        $moduleName = trim((string)$moduleName);
        $page = trim((string)$page);
        if ($moduleName === '') {
            return '';
        }
        if ($page === '') {
            $page = 'index.php';
        }

        return '../modules/' . rawurlencode($moduleName) . '/' . $page;
    }
}

if (!function_exists('itm_script_module_relative_href_from_path')) {
    function itm_script_module_relative_href_from_path($modulePath, $page = 'index.php'): string
    {
        $modulePath = trim((string)$modulePath, '/');
        $page = trim((string)$page);
        if ($modulePath === '') {
            return '';
        }
        if ($page === '') {
            $page = 'index.php';
        }

        return '../' . $modulePath . '/' . $page;
    }
}

if (!function_exists('itm_script_external_link_html')) {
    /**
     * @param string $href
     * @param string $label
     */
    function itm_script_external_link_html($href, $label): string
    {
        $href = (string)$href;
        if ($href === '') {
            return htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
        }

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
            . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }
}

if (!function_exists('itm_script_format_module_link')) {
    /**
     * @param string $moduleName Folder under modules/ (no path prefix)
     * @param string $baseUrl Unused; kept for callers
     * @param string $label Optional link text (defaults to module folder name)
     */
    function itm_script_format_module_link($moduleName, $baseUrl = '', $label = ''): string
    {
        $moduleName = trim((string)$moduleName);
        if ($moduleName === '') {
            return '';
        }
        if ($label === '') {
            $label = $moduleName;
        }
        if (itm_script_is_cli_sapi()) {
            return (string)$label;
        }

        return itm_script_external_link_html(
            itm_script_module_relative_href($moduleName),
            (string)$label
        );
    }
}

if (!function_exists('itm_script_format_module_path_link')) {
    /**
     * @param string $modulePath e.g. modules/catalogs/
     * @param string $label Optional link text
     */
    function itm_script_format_module_path_link($modulePath, $label = ''): string
    {
        $modulePath = trim((string)$modulePath);
        if ($modulePath === '') {
            return '';
        }
        if ($label === '') {
            $label = trim(str_replace('\\', '/', $modulePath), '/');
        }
        if (itm_script_is_cli_sapi()) {
            return (string)$label;
        }

        return itm_script_external_link_html(
            itm_script_module_relative_href_from_path($modulePath),
            (string)$label
        );
    }
}

if (!function_exists('itm_script_format_table_link')) {
    /**
     * Link table name to modules/&lt;table&gt;/ when that module exists; otherwise plain text.
     *
     * @param bool $linkAsModulePath When true, always link to modules/&lt;table&gt;/index.php even if the folder is missing.
     */
    function itm_script_format_table_link($tableName, $baseUrl = '', bool $linkAsModulePath = false): string
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return '';
        }
        if (itm_script_is_cli_sapi()) {
            return $linkAsModulePath ? 'modules/' . $tableName . '/index.php' : $tableName;
        }
        if ($linkAsModulePath || itm_script_table_has_module($tableName)) {
            return itm_script_format_module_link($tableName, $baseUrl);
        }

        return htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('itm_script_format_modules_file_link')) {
    /**
     * Link modules/&lt;slug&gt;/… repo paths to the module index in browser script output.
     *
     * @param string $repoRelativePath e.g. modules/patches_updates/create.php
     * @param int $line Optional 1-based line suffix (0 = omit)
     */
    function itm_script_format_modules_file_link(string $repoRelativePath, int $line = 0): string
    {
        $repoRelativePath = trim(str_replace('\\', '/', $repoRelativePath), '/');
        if ($repoRelativePath === '') {
            return '';
        }

        $lineSuffix = $line > 0 ? ':' . $line : '';
        if (itm_script_is_cli_sapi()) {
            return $repoRelativePath . $lineSuffix;
        }

        if (!preg_match('#^modules/([^/]+)(?:/(.*))?$#', $repoRelativePath, $matches)) {
            return htmlspecialchars($repoRelativePath, ENT_QUOTES, 'UTF-8')
                . htmlspecialchars($lineSuffix, ENT_QUOTES, 'UTF-8');
        }

        $moduleSlug = (string)$matches[1];
        $remainder = isset($matches[2]) ? (string)$matches[2] : '';
        $moduleLink = itm_script_format_module_link($moduleSlug, '', $moduleSlug);
        if ($remainder === '') {
            return $moduleLink . htmlspecialchars($lineSuffix, ENT_QUOTES, 'UTF-8');
        }

        return $moduleLink . ' '
            . htmlspecialchars($remainder, ENT_QUOTES, 'UTF-8')
            . htmlspecialchars($lineSuffix, ENT_QUOTES, 'UTF-8');
    }
}
