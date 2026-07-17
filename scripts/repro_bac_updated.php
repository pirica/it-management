<?php
/**
 * Validation for IDFs API BAC fix.
 * Why: idfs is RBAC-exempt; verify tenant scoping blocks company 1 from deleting company 2 positions.
 */
$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_repro_idfs_bac.php';

itm_script_output_begin('Verify: IDFs API BAC Fix');
$nl = itm_script_output_nl();

$attacker_company_id = 1;
$victim_company_id = 2;
$testUser = itm_script_test_employee_create($conn, $attacker_company_id, ['script_slug' => 'val-idfs-api-bac', 'role_id' => 5]);
if (!is_array($testUser)) {
    die("Failed to create test user\n");
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

echo colorText('Validating cross-tenant BAC in IDFs API', 'info') . $nl;

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

if ($exists && is_array($decoded) && empty($decoded['ok'])) {
    echo itm_script_format_status_line('[PASS] SUCCESS: Victim position still exists. Cross-tenant delete was blocked.') . $nl;
    echo 'API response: ' . (string)($decoded['error'] ?? 'denied') . $nl;
} elseif ($exists) {
    echo itm_script_format_status_line('[PASS] SUCCESS: Victim position still exists after API call.') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] FAILURE: Victim position was deleted across tenants.') . $nl;
    echo 'Output: ' . $output . $nl;
    $exitCode = 1;
}

itm_repro_idfs_cleanup_idf($conn, $victim_company_id, $idf_id);
itm_script_output_end();
exit($exitCode);
