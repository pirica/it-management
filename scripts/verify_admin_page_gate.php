<?php
/**
 * Admin page gate regression checks.
 *
 * CLI: php scripts/verify_admin_page_gate.php
 * Browser: scripts/verify_admin_page_gate.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Admin Page Gate Verification');

$nl = itm_script_output_nl();
$failures = 0;

function apg_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function apg_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$adminPath = ROOT_PATH . 'admin.php';
if (!is_file($adminPath)) {
    apg_verify_fail('Missing admin.php');
    itm_script_output_end();
    exit(1);
}

$adminSource = (string)file_get_contents($adminPath);

if (strpos($adminSource, 'itm_is_admin(') === false) {
    apg_verify_fail('admin.php must call itm_is_admin()');
} else {
    apg_verify_pass('admin.php calls itm_is_admin()');
}

if (strpos($adminSource, "header('Location: ' . BASE_URL . 'dashboard.php')") === false
    && strpos($adminSource, 'header(\'Location: \' . BASE_URL . \'dashboard.php\')') === false) {
    apg_verify_fail('admin.php must redirect non-admins to dashboard.php');
} else {
    apg_verify_pass('admin.php redirects non-admins to dashboard.php');
}

if (strpos($adminSource, 'admin.php') === false) {
    apg_verify_fail('admin.php company switch redirect should target admin.php');
} else {
    apg_verify_pass('admin.php references admin.php for post-switch redirect');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All admin page gate checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
