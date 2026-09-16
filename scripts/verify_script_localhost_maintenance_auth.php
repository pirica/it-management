<?php
/**
 * Regression: browser QA scripts require Administrator session (ITM-PENTEST-008 remediated).
 *
 * CLI: php scripts/verify_script_localhost_maintenance_auth.php
 * Browser: scripts/verify_script_localhost_maintenance_auth.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_script_localhost_maintenance_auth.php</code> — exit <code>1</code> on failure. Confirms MBQA runner and PHPUnit browser menu do not skip portal login via localhost or <code>ITM_MAINTENANCE_TOKEN</code>.
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

$configSource = (string) file_get_contents(dirname(__DIR__) . '/config/config.php');
$bootstrapSource = (string) file_get_contents(__DIR__ . '/lib/itm_script_bootstrap.php');
$gateSource = (string) file_get_contents(dirname(__DIR__) . '/includes/itm_maintenance_script_admin_gate.php');

if (preg_match(
    '/itm_script_browser_maintenance_skip_web_auth_applies\(\)[\s\S]{0,120}\$itmSkipWebAuth\s*=\s*true/s',
    $configSource
) === 1) {
    vsl_fail('config.php must not set $itmSkipWebAuth from maintenance localhost/token bypass');
} else {
    vsl_pass('config.php does not wire maintenance web-auth skip for browser QA scripts');
}

if (!function_exists('itm_script_browser_maintenance_skip_web_auth_applies')
    || itm_script_browser_maintenance_skip_web_auth_applies() !== false) {
    vsl_fail('itm_script_browser_maintenance_skip_web_auth_applies() must always return false');
} else {
    vsl_pass('Maintenance web-auth bypass helper disabled');
}

if (!function_exists('itm_script_browser_maintenance_skip_admin_applies')
    || itm_script_browser_maintenance_skip_admin_applies() !== false) {
    vsl_fail('itm_script_browser_maintenance_skip_admin_applies() must always return false');
} else {
    vsl_pass('Maintenance skip-admin helper disabled');
}

$allowlist = function_exists('itm_script_browser_skip_web_auth_allowlist')
    ? itm_script_browser_skip_web_auth_allowlist()
    : null;
if ($allowlist !== []) {
    vsl_fail('Maintenance skip-web-auth allowlist must be empty');
} else {
    vsl_pass('Maintenance skip-web-auth allowlist is empty');
}

$skipAdminList = function_exists('itm_script_browser_maintenance_skip_admin_basenames')
    ? itm_script_browser_maintenance_skip_admin_basenames()
    : null;
if ($skipAdminList !== []) {
    vsl_fail('Maintenance skip-admin list must be empty');
} else {
    vsl_pass('Maintenance skip-admin list is empty');
}

if (strpos($gateSource, 'itm_script_browser_maintenance_skip_admin_applies') !== false) {
    vsl_fail('Maintenance admin gate must not skip Administrator for run_tests.php');
} else {
    vsl_pass('Maintenance admin gate always requires Administrator in browser');
}

$runTestsSource = (string) file_get_contents(__DIR__ . '/run_tests.php');
if (strpos($runTestsSource, 'itm_enforce_maintenance_script_admin_browser') === false) {
    vsl_fail('run_tests.php must call itm_enforce_maintenance_script_admin_browser() for browser access');
} else {
    vsl_pass('run_tests.php enforces Administrator browser gate');
}

if (strpos($runTestsSource, 'if ($isCli)') === false
    || !preg_match('/if\s*\(\s*\$isCli\s*\)\s*\{[^}]*putenv\(\'ITM_SKIP_DB_TESTS=1\'\)/s', $runTestsSource)) {
    vsl_fail('run_tests.php must set ITM_SKIP_DB_TESTS before config only on CLI (browser needs $conn for Admin gate)');
} else {
    vsl_pass('run_tests.php keeps DB connection on browser load for Administrator gate');
}

$mbqaSource = (string) file_get_contents(__DIR__ . '/module_browser_qa_runner.php');
if (strpos($mbqaSource, 'itm_enforce_maintenance_script_admin_browser') === false) {
    vsl_fail('module_browser_qa_runner.php must call itm_enforce_maintenance_script_admin_browser()');
} else {
    vsl_pass('MBQA runner enforces Administrator browser gate');
}

if (strpos($bootstrapSource, 'itm_script_browser_no_auth_client_allowed') === false
    || strpos($bootstrapSource, 'ITM_SCRIPT_NO_AUTH_ALLOWED_IPS') === false) {
    vsl_fail('No-auth scripts must remain gated by itm_script_browser_no_auth_client_allowed()');
} else {
    vsl_pass('No-auth browser scripts still use loopback/host/IP/maintenance-token gate');
}

if (strpos($configSource, 'itm_script_browser_no_auth_client_allowed') === false) {
    vsl_fail('config.php must call itm_script_browser_no_auth_client_allowed() for ITM_SCRIPT_NO_AUTH');
} else {
    vsl_pass('config.php enforces no-auth IP / maintenance-token gate');
}

if (strpos($configSource, '$itmSkipWebAuth = PHP_SAPI === \'cli\' && defined(\'ITM_CLI_SCRIPT\')') === false) {
    vsl_fail('CLI verify scripts must use ITM_CLI_SCRIPT skip (separate from browser maintenance bypass)');
} else {
    vsl_pass('CLI ITM_CLI_SCRIPT auth skip documented separately from browser QA');
}

if ($failures > 0) {
    echo colorText('SUMMARY: Script maintenance auth checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Browser QA scripts require Admin session; maintenance bypass removed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
