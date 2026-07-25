<?php
/**
 * Verifies maintenance/security scripts enforce Admin gate in browser mode.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Maintenance Scripts RBAC Verification');

$nl = itm_script_output_nl();
$required = [
    __DIR__ . '/compare_database_sql_modules.php',
    __DIR__ . '/test_sql_injection.php',
    __DIR__ . '/module_browser_qa_runner.php',
    dirname(__DIR__) . '/includes/itm_maintenance_script_admin_gate.php',
];

$failed = false;
foreach ($required as $path) {
    $label = str_replace(ROOT_PATH, '', str_replace('\\', '/', $path));
    $content = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($content === '' || strpos($content, 'itm_enforce_maintenance_script_admin_browser') === false) {
        echo colorText('[FAIL] Missing RBAC guard in ' . $label, 'fail') . $nl;
        $failed = true;
        continue;
    }
    echo colorText('[PASS] RBAC guard present in ' . $label, 'pass') . $nl;
}

itm_script_output_end();
exit($failed ? 1 : 0);
