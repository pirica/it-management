<?php
/**
 * Regression: count_db_tables.php low-impact recon contract.
 *
 * Intentionally no-auth for deploy monitors — exposes only a table total, not names/schema.
 *
 * CLI: php scripts/verify_count_db_tables_recon.php
 * Browser: scripts/verify_count_db_tables_recon.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_count_db_tables_recon.php</code> — exit <code>1</code> on failure. Run when changing <code>scripts/count_db_tables.php</code> or no-auth script allowlist in <code>config/config.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('count_db_tables recon contract verification');

$nl = itm_script_output_nl();
$failures = 0;

function vcd_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vcd_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$scriptPath = __DIR__ . '/count_db_tables.php';
$scriptSource = is_file($scriptPath) ? (string) file_get_contents($scriptPath) : '';
if ($scriptSource === '' || strpos($scriptSource, 'ITM_SCRIPT_NO_AUTH') === false) {
    vcd_fail('count_db_tables.php must define ITM_SCRIPT_NO_AUTH before config.php');
} else {
    vcd_pass('count_db_tables.php declares ITM_SCRIPT_NO_AUTH');
}

$configSource = (string) file_get_contents(dirname(__DIR__) . '/config/config.php');
$bootstrapSource = (string) file_get_contents(__DIR__ . '/lib/itm_script_bootstrap.php');
if (strpos($configSource, "'count_db_tables.php'") === false) {
    vcd_fail('config.php no-auth allowlist must include count_db_tables.php');
} else {
    vcd_pass('count_db_tables.php listed in config.php no-auth allowlist');
}

if (strpos($bootstrapSource, 'itm_script_browser_no_auth_client_allowed') === false) {
    vcd_fail('No-auth scripts must use itm_script_browser_no_auth_client_allowed() IP/token gate');
} else {
    vcd_pass('No-auth IP allowlist gate present in itm_script_bootstrap.php');
}

if (preg_match('/TABLE_NAME|SHOW TABLES|DESCRIBE/i', $scriptSource) === 1) {
    vcd_fail('count_db_tables.php must not enumerate table names in SQL or output');
} else {
    vcd_pass('Script source avoids table-name enumeration');
}

if (strpos($scriptSource, 'number_db_tables.txt') !== false || strpos($scriptSource, 'file_put_contents') !== false) {
    vcd_fail('count_db_tables.php must not write number_db_tables.txt or other files');
} else {
    vcd_pass('Script outputs table count only (no file mirror)');
}

if (!function_exists('shell_exec')) {
    vcd_fail('shell_exec unavailable — cannot run live count_db_tables subprocess');
} else {
    $phpBin = itm_resolve_cli_php_binary();
    $output = shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath) . ' 2>&1');
    $output = is_string($output) ? trim($output) : '';
    if ($output === '' || !preg_match('/^\d+$/', $output)) {
        vcd_fail('CLI output must be digits-only table count (got: ' . substr($output, 0, 40) . ')');
    } else {
        vcd_pass('CLI emits digits-only table count');
    }

    $count = (int) $output;
    if ($count <= 0) {
        vcd_fail('Table count must be positive on a seeded database');
    } else {
        vcd_pass('Live table count is positive (' . $count . ')');
    }
}

if ($failures > 0) {
    echo colorText('SUMMARY: count_db_tables recon contract checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: count_db_tables recon contract checks passed (low-impact aggregate only).', 'pass') . $nl;
itm_script_output_end();
exit(0);
