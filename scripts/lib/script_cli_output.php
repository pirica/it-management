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

    /** Why: SCRIPTS.md cross-environment newline for CLI and browser script output. */
    function itm_script_output_nl()
    {
        return itm_script_cli_is_cli() ? "\n" : '<br><br>';
    }

    /** Why: Colour pass/fail/warn/info prefixes when present; leave other lines unchanged. */
    function itm_script_format_status_line($message)
    {
        $text = (string) $message;
        if (preg_match('/^\[(PASS|OK)\]/', $text)) {
            return colorText($text, 'pass');
        }
        if (preg_match('/^\[FAIL\]/', $text)) {
            return colorText($text, 'fail');
        }
        if (preg_match('/^\[WARN\]/', $text)) {
            return colorText($text, 'warn');
        }
        if (preg_match('/^\[INFO\]/', $text)) {
            return colorText($text, 'info');
        }
        return $text;
    }

    /** Why: Discard subprocess stderr on Windows (2>NUL) and POSIX (2>/dev/null) when spawning isolated PHP from repro/verify scripts. */
    function itm_script_shell_stderr_discard(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    }

    /** Why: SCRIPTS.md cross-environment pass/fail/warn/info colouring for CLI and browser audits. */
    function colorText($text, $type)
    {
        $isCli = itm_script_cli_is_cli();

        switch ($type) {
            case 'pass':
                return $isCli
                    ? "\033[32m$text\033[0m"
                    : "<span style='color: green;'>$text</span>";

            case 'fail':
                return $isCli
                    ? "\033[31m$text\033[0m"
                    : "<span style='color: red;'>$text</span>";

            case 'warn':
                return $isCli
                    ? "\033[33m$text\033[0m"
                    : "<span style='color: goldenrod;'>$text</span>";

            case 'info':
                return $isCli
                    ? "\033[34m$text\033[0m"
                    : "<span style='color: dodgerblue;'>$text</span>";

            default:
                return $text;
        }
    }

    /** Print a headed list with real newlines (readable inside browser <pre>). */
    function itm_script_echo_list($heading, array $items)
    {
        $items = array_values(array_unique(array_map('strval', $items)));
        sort($items, SORT_STRING);
        echo (string)$heading . ":\n";
        if ($items === []) {
            echo "  (none)\n\n";
            return;
        }
        foreach ($items as $item) {
            echo '  - ' . $item . "\n";
        }
        echo "\n";
    }
}
