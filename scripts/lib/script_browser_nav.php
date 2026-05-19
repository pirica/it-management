<?php
/**
 * Shared navigation and outbound links for browser-run scripts under scripts/.
 *
 * Why: Every HTML report should link back to the scripts catalog and deep-link modules/tables.
 */

if (!function_exists('itm_script_is_cli_sapi')) {
    function itm_script_is_cli_sapi(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('itm_script_browser_nav_html')) {
    /**
     * @param string $baseUrl App BASE_URL with trailing slash, or empty for relative scripts/ paths
     */
    function itm_script_browser_nav_html($baseUrl = ''): string
    {
        $indexHref = 'index.html';
        if ($baseUrl !== '') {
            $indexHref = rtrim((string)$baseUrl, '/') . '/scripts/index.html';
        }

        $indexEsc = htmlspecialchars($indexHref, ENT_QUOTES, 'UTF-8');

        return '<nav class="itm-script-nav" aria-label="Scripts navigation" style="margin:0 0 16px;padding:10px 14px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:0.9rem;">'
            . '<a href="' . $indexEsc . '" style="color:#0969da;text-decoration:none;font-weight:600;">← Scripts index</a>'
            . '</nav>';
    }

    function itm_script_browser_nav_echo($baseUrl = ''): void
    {
        echo itm_script_browser_nav_html($baseUrl);
    }
}

if (!function_exists('itm_script_default_database_name')) {
    function itm_script_default_database_name(): string
    {
        if (defined('DB_NAME') && (string)DB_NAME !== '') {
            return (string)DB_NAME;
        }
        $fromEnv = getenv('DB_NAME');
        if ($fromEnv !== false && (string)$fromEnv !== '') {
            return (string)$fromEnv;
        }

        return 'itmanagement';
    }
}

if (!function_exists('itm_script_phpmyadmin_table_url')) {
    function itm_script_phpmyadmin_table_url($tableName, $databaseName = ''): string
    {
        $tableName = (string)$tableName;
        $databaseName = (string)$databaseName;
        if ($databaseName === '') {
            $databaseName = itm_script_default_database_name();
        }

        return 'http://localhost/phpmyadmin/index.php?route=/table/sql&db='
            . rawurlencode($databaseName)
            . '&table='
            . rawurlencode($tableName);
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

if (!function_exists('itm_script_module_index_url')) {
    function itm_script_module_index_url($baseUrl, $modulePath): string
    {
        $modulePath = (string)$modulePath;
        if ($modulePath === '') {
            return '';
        }

        return rtrim((string)$baseUrl, '/') . '/' . ltrim($modulePath, '/');
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
     * @param string $baseUrl Optional BASE_URL with trailing slash
     */
    function itm_script_format_module_link($moduleName, $baseUrl = ''): string
    {
        $moduleName = trim((string)$moduleName);
        if ($moduleName === '') {
            return '';
        }
        if (itm_script_is_cli_sapi()) {
            return $moduleName;
        }

        if ($baseUrl !== '') {
            $href = itm_script_module_index_url($baseUrl, 'modules/' . $moduleName . '/index.php');
        } else {
            $href = '../modules/' . rawurlencode($moduleName) . '/index.php';
        }

        return itm_script_external_link_html($href, $moduleName);
    }
}

if (!function_exists('itm_script_format_table_link')) {
    function itm_script_format_table_link($tableName, $databaseName = ''): string
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return '';
        }
        if (itm_script_is_cli_sapi()) {
            return $tableName;
        }

        return itm_script_external_link_html(
            itm_script_phpmyadmin_table_url($tableName, $databaseName),
            $tableName
        );
    }
}
