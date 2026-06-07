<?php
/**
 * Why: Security audit scripts print plain text for CI; in a browser, wrapping output in
 * <pre> keeps line breaks and column alignment without per-script <br> hacks.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'script_browser_nav.php';

if (!function_exists('itm_script_cli_is_cli')) {
    function itm_script_cli_is_cli()
    {
        return itm_script_is_cli_sapi();
    }

    function itm_script_output_begin($pageTitle = 'Script output')
    {
        static $opened = false;
        if ($opened || itm_script_cli_is_cli()) {
            return;
        }
        $opened = true;

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $title = htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . $title
            . '</title><style>body{font-family:Consolas,"Courier New",monospace;font-size:13px;margin:16px;line-height:1.4;}pre{margin:0;white-space:pre-wrap;word-break:break-word;}</style></head><body>';
        itm_script_browser_nav_echo();
        echo '<pre>';

        register_shutdown_function('itm_script_output_end');
    }

    /** Close the log <pre> so scripts can echo HTML footers before the document ends. */
    function itm_script_output_close_pre(): void
    {
        if (itm_script_cli_is_cli()) {
            return;
        }
        $GLOBALS['itm_script_pre_closed'] = true;
        echo '</pre>';
    }

    function itm_script_output_end()
    {
        static $closed = false;
        if ($closed || itm_script_cli_is_cli()) {
            return;
        }
        $closed = true;
        if (empty($GLOBALS['itm_script_pre_closed'])) {
            echo '</pre>';
        }
        echo '</body></html>';
    }
}
