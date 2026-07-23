<?php
/**
 * Shared helpers for scripts browser/CLI access modes.
 *
 * Why: Centralize check-script Admin gates and CLI-only instruction pages so
 * catalog badges (Browser / CLI / Dry-run / CLI-only) match runtime behaviour.
 */

if (!function_exists('itm_script_access_is_cli')) {
    /**
     * @return bool
     */
    function itm_script_access_is_cli()
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('itm_check_script_begin_browser_admin')) {
    /**
     * Start output for static check scripts (Browser + CLI, Admin in browser).
     *
     * @param string $title
     * @return string Newline for output (from itm_script_output_nl()).
     */
    function itm_check_script_begin_browser_admin($title)
    {
        require_once __DIR__ . '/script_cli_output.php';

        if (!itm_script_access_is_cli()) {
            require_once dirname(__DIR__, 2) . '/config/config.php';
            itm_script_require_admin_script_or_exit(
                $conn,
                'Access denied. Administrator privileges required.'
            );
        }

        itm_script_output_begin((string)$title);

        return itm_script_output_nl();
    }
}

if (!function_exists('itm_script_echo_cli_only_page')) {
    /**
     * Small HTML page for scripts that cannot run in the browser.
     *
     * @param string $title
     * @param string $descriptionHtml Safe HTML body (no user input).
     * @param string $cliCommand Example command without escaping.
     * @return void
     */
    function itm_script_echo_cli_only_page($title, $descriptionHtml, $cliCommand)
    {
        require_once __DIR__ . '/script_browser_nav.php';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>';
        echo htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
        echo '</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
        itm_script_browser_nav_echo();
        echo '<p><strong>CLI-only.</strong> ';
        echo $descriptionHtml;
        echo '</p>';
        echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">';
        echo htmlspecialchars((string)$cliCommand, ENT_QUOTES, 'UTF-8');
        echo '</pre></body></html>';
    }
}
