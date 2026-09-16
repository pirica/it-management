<?php
/**
 * Entry-point guards for PHPUnit HTML coverage (processUncoveredFiles).
 *
 * Why: Bare require of HTTP endpoints, CLI scripts, or view partials during
 * coverage finalization must not send headers, echo HTML, or exit().
 */

if (!function_exists('itm_is_phpunit_processing')) {
    /**
     * True while PHPUnit / php-code-coverage is requiring files (processUncoveredFiles).
     */
    function itm_is_phpunit_processing()
    {
        if (class_exists('PHPUnit\\Runner\\Version', false)
            || class_exists('PHPUnit\\Framework\\TestCase', false)
            || defined('PHPUNIT_COMPOSER_INSTALL')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
            foreach ($trace as $frame) {
                $file = isset($frame['file']) ? str_replace('\\', '/', (string)$frame['file']) : '';
                if ($file === '') {
                    continue;
                }
                if (strpos($file, '/phpunit/') !== false
                    || strpos($file, 'phpunit.phar') !== false
                    || strpos($file, 'php-code-coverage') !== false
                    || strpos($file, 'CodeCoverage') !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('itm_is_script_direct_entry')) {
    /**
     * True when this file is the running script (HTTP or CLI), not a bare include.
     */
    function itm_is_script_direct_entry($file = __FILE__)
    {
        $scriptFilename = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($scriptFilename === '') {
            return false;
        }
        $fileReal = @realpath($file);
        $scriptReal = @realpath($scriptFilename);
        if ($fileReal === false || $scriptReal === false) {
            return false;
        }

        return $fileReal === $scriptReal;
    }
}

if (!function_exists('itm_skip_http_entry_unless_direct')) {
    /**
     * HTTP JSON/redirect endpoints: return true when execution should stop (bare include / CLI).
     */
    function itm_skip_http_entry_unless_direct($file = __FILE__)
    {
        // Why: Contract-test subprocesses include HTTP endpoints under CLI with stubs for CSRF/JSON exit.
        // Threat model: only trusted scripts under scripts/ may define ITM_HTTP_ENDPOINT_CONTRACT_TEST;
        // never derive this constant from HTTP input (would bypass CLI entry guards).
        if (defined('ITM_HTTP_ENDPOINT_CONTRACT_TEST') && ITM_HTTP_ENDPOINT_CONTRACT_TEST) {
            return false;
        }
        if (itm_is_phpunit_processing()) {
            return true;
        }
        if (PHP_SAPI === 'cli') {
            return true;
        }

        return !itm_is_script_direct_entry($file);
    }
}

if (!function_exists('itm_skip_cli_script_unless_direct')) {
    /**
     * CLI maintenance scripts: return true when execution should stop (bare include during coverage).
     */
    function itm_skip_cli_script_unless_direct($file = __FILE__)
    {
        if (itm_is_phpunit_processing()) {
            return true;
        }
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        return !itm_is_script_direct_entry($file);
    }
}

if (!function_exists('itm_skip_view_partial_unless_context')) {
    /**
     * View partials (header, sidebar, module fragments): skip HTML when required without layout context.
     */
    function itm_skip_view_partial_unless_context($requireConn = true, $file = __FILE__)
    {
        if (itm_is_phpunit_processing()) {
            return true;
        }
        if (itm_is_script_direct_entry($file)) {
            return true;
        }
        if (PHP_SAPI === 'cli') {
            return true;
        }
        if ($requireConn && (!isset($conn) || !($conn instanceof mysqli))) {
            return true;
        }

        return false;
    }
}
