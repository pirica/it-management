<?php
/**
 * PoC for Broken Access Control in IDFs API.
 * Why: idfs is RBAC-exempt; BAC is tenant scoping — company 1 must not delete company 2 positions.
 */
$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/itm_repro_idfs_bac.php';

$attacker_company_id = 1;
$victim_company_id = 2;
$testUser = itm_script_test_employee_create($conn, $attacker_company_id, ['script_slug' => 'repro-idfs-api-bac', 'role_id' => 5]);
if (!is_array($testUser)) {
    die("Failed to create test user\n");
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: IDFs API BAC');
$nl = itm_script_output_nl();

echo colorText('Testing cross-tenant BAC in modules/idfs/api/position_delete.php', 'info') . $nl;

$labelSuffix = str_replace('.', '', (string)microtime(true));
$seed = itm_repro_idfs_seed_test_position($conn, $victim_company_id, $labelSuffix);
if (!is_array($seed)) {
    echo itm_script_format_status_line('[FAIL] Could not seed victim IDF position in company ' . $victim_company_id . '.') . $nl;
    itm_script_output_end();
    exit(1);
}

$idf_id = (int)$seed['idf_id'];
$position_id = (int)$seed['position_id'];

$sessionData = [
    'company_id' => $attacker_company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
];

$output = itm_repro_idfs_run_position_delete_subprocess($sessionData, $position_id);
$decoded = itm_repro_idfs_parse_api_json($output);
$exists = itm_repro_idfs_position_exists($conn, $victim_company_id, $position_id);
$exitCode = 0;

if (!$exists) {
    echo itm_script_format_status_line('[FAIL] VULNERABILITY CONFIRMED: Cross-tenant position delete succeeded.') . $nl;
    echo 'Output: ' . $output . $nl;
    $exitCode = 1;
} elseif (is_array($decoded) && !empty($decoded['ok'])) {
    echo itm_script_format_status_line('[FAIL] API returned ok:true but victim row still exists (unexpected).') . $nl;
    $exitCode = 1;
} else {
    echo itm_script_format_status_line('[PASS] Cross-tenant position delete was blocked.') . $nl;
    if (is_array($decoded)) {
        echo itm_script_format_status_line('[PASS] API response: ' . (string)($decoded['error'] ?? 'denied')) . $nl;
    }
}

itm_repro_idfs_cleanup_idf($conn, $victim_company_id, $idf_id);
itm_script_output_end();
exit($exitCode);
