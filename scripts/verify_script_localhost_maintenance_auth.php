<?php
/**
 * Regression: maintenance script localhost / ITM_MAINTENANCE_TOKEN bypass contract.
 *
 * Deployment-dependent: only allowlisted scripts may skip browser auth from
 * 127.0.0.1/::1 or with a valid maintenance token.
 *
 * CLI: php scripts/verify_script_localhost_maintenance_auth.php
 * Browser: scripts/verify_script_localhost_maintenance_auth.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_script_localhost_maintenance_auth.php</code> — exit <code>1</code> on failure. Run when changing <code>config/config.php</code> script auth or <code>scripts/lib/itm_script_bootstrap.php</code> maintenance allowlist.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Script localhost maintenance auth verification');

$nl = itm_script_output_nl();
$failures = 0;

function vsl_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vsl_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$allowlist = function_exists('itm_script_browser_skip_web_auth_allowlist')
    ? itm_script_browser_skip_web_auth_allowlist()
    : [];
$expectedAllowlist = [
    'module_browser_qa_runner.php',
    'run_tests.php',
];
sort($allowlist);
$expectedSorted = $expectedAllowlist;
sort($expectedSorted);
if ($allowlist !== $expectedSorted) {
    vsl_fail('Maintenance allowlist must remain module_browser_qa_runner.php + run_tests.php only');
} else {
    vsl_pass('Maintenance allowlist limited to MBQA runner and PHPUnit menu');
}

$configSource = (string) file_get_contents(dirname(__DIR__) . '/config/config.php');
if (strpos($configSource, 'ITM_MAINTENANCE_TOKEN') === false
    || strpos($configSource, '$itmIsLocalhost') === false
    || strpos($configSource, 'hash_equals($itmMaintToken') === false) {
    vsl_fail('config.php must gate skip-web-auth on localhost OR valid ITM_MAINTENANCE_TOKEN');
} else {
    vsl_pass('config.php documents localhost + maintenance token gate');
}

if (strpos($configSource, 'PHP_SAPI !== \'cli\'') === false
    || !preg_match('/Browser:\s*MBQA runner[\s\S]*PHP_SAPI !== \'cli\'/', $configSource)) {
    vsl_fail('Maintenance localhost bypass must be browser-only (PHP_SAPI !== cli guard)');
} else {
    vsl_pass('Maintenance bypass block is browser-only, not CLI');
}

if (strpos($configSource, '127.0.0.1') === false || strpos($configSource, '::1') === false) {
    vsl_fail('config.php must treat 127.0.0.1 and ::1 as localhost for maintenance scripts');
} else {
    vsl_pass('Localhost detection includes IPv4 loopback and ::1');
}

if (in_array('verify_attempts_view_rbac.php', $allowlist, true)
    || in_array('scripts.php', $allowlist, true)) {
    vsl_fail('General verify scripts and catalog must not be on maintenance allowlist');
} else {
    vsl_pass('Maintenance allowlist excludes general verify scripts');
}

if (strpos($configSource, '$itmSkipWebAuth = PHP_SAPI === \'cli\' && defined(\'ITM_CLI_SCRIPT\')') === false) {
    vsl_fail('CLI verify scripts must use ITM_CLI_SCRIPT skip (separate from browser localhost bypass)');
} else {
    vsl_pass('CLI ITM_CLI_SCRIPT auth skip documented separately from browser maintenance bypass');
}

if ($failures > 0) {
    echo colorText('SUMMARY: Script maintenance auth checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Script maintenance auth checks passed (deployment still must not expose scripts publicly).', 'pass') . $nl;
itm_script_output_end();
exit(0);
