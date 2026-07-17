<?php
/**
 * Standard top-of-file include for CLI-only scripts/* regressions.
 *
 * Why: require config.php at file scope so $conn and other bootstrap vars stay visible
 * to the script; disposable test sessions use itm_script_with_test_session_context().
 */
require_once __DIR__ . '/itm_script_bootstrap.php';

$itmScriptCliEntryBasename = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (!itm_script_is_cli()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo ($itmScriptCliEntryBasename !== '' ? $itmScriptCliEntryBasename : 'script') . " is CLI-only. Run from the repository root:\n";
    echo 'php scripts/' . ($itmScriptCliEntryBasename !== '' ? $itmScriptCliEntryBasename : '<script>.php') . "\n";
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/../config/config.php';
