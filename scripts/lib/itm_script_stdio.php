<?php
/**
 * Safe STDOUT/STDERR writes for CLI and browser SAPIs (Apache has no STDOUT resource).
 */

if (!function_exists('itm_script_stdio_stream')) {
    /**
     * @param string $constantName STDOUT or STDERR
     * @return resource|null
     */
    function itm_script_stdio_stream($constantName)
    {
        if (!is_string($constantName) || !defined($constantName)) {
            return null;
        }
        $stream = constant($constantName);

        return is_resource($stream) ? $stream : null;
    }
}

if (!function_exists('itm_script_write_stdout')) {
    function itm_script_write_stdout($message)
    {
        $out = itm_script_stdio_stream('STDOUT');
        if ($out !== null) {
            fwrite($out, (string) $message);

            return;
        }
        echo (string) $message;
    }
}

if (!function_exists('itm_script_write_stderr')) {
    function itm_script_write_stderr($message)
    {
        $err = itm_script_stdio_stream('STDERR');
        if ($err !== null) {
            fwrite($err, (string) $message);

            return;
        }
        echo (string) $message;
    }
}
