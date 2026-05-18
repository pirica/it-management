<?php
/**
 * Why: Security audit scripts print plain text for CI; in a browser, wrapping output in
 * <pre> keeps line breaks and column alignment without per-script <br> hacks.
 */

if (!function_exists('itm_script_cli_is_cli')) {
    function itm_script_cli_is_cli()
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
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
            . '</title><style>body{font-family:Consolas,"Courier New",monospace;font-size:13px;margin:16px;line-height:1.4;}pre{margin:0;white-space:pre-wrap;word-break:break-word;}</style></head><body><pre>';

        register_shutdown_function('itm_script_output_end');
    }

    function itm_script_output_end()
    {
        static $closed = false;
        if ($closed || itm_script_cli_is_cli()) {
            return;
        }
        $closed = true;
        echo '</pre><p style="font-family:sans-serif;font-size:14px;"><a href="index.html">← Scripts index</a></p></body></html>';
    }
}
