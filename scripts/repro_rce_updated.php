<?php
/**
 * Validation for Floor Designer RCE fix.
 */
$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/../modules/floor_plans/gallery_helpers.php';
require_once __DIR__ . '/lib/itm_repro_floor_designer_rce.php';

itm_script_output_begin('Verify: Floor Designer RCE Fix');
$nl = itm_script_output_nl();

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-floor-rce']);
if (!is_array($testUser)) {
    die("Failed to create test user\n");
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

echo colorText('Validating RCE Fix in Floor Designer', 'info') . $nl;

$sampleDataUri = itm_repro_floor_designer_sample_png_data_uri();
if ($sampleDataUri === '') {
    echo itm_script_format_status_line('[FAIL] Sample PNG missing under images/switch_port_icons/.') . $nl;
    itm_script_output_end();
    exit(1);
}

$uploadDir = fp_company_upload_dir($company_id);
$phpBefore = glob($uploadDir . 'floor_plan_*.php') ?: [];
$testName = 'RCE Val ' . str_replace('.', '', (string)microtime(true));

$sessionData = [
    'company_id' => $company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'admin',
];

$postData = [
    'ajax_action' => 'save_as_floor_plan',
    'name' => $testName,
    'ext' => 'php',
    'data' => $sampleDataUri,
];

$output = itm_repro_floor_designer_run_save_subprocess($sessionData, $postData);
$decoded = itm_repro_floor_designer_parse_json_response($output);
if (!is_array($decoded)) {
    echo itm_script_format_status_line('[FAIL] save_as_floor_plan handler did not return JSON.') . $nl;
    itm_script_output_end();
    exit(1);
}

$planId = (int)($decoded['id'] ?? 0);
$storedFilename = '';
if ($planId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT stored_filename FROM floor_plans WHERE id = ? AND company_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $planId, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (is_array($row)) {
        $storedFilename = (string)($row['stored_filename'] ?? '');
    }
}

$phpAfter = glob($uploadDir . 'floor_plan_*.php') ?: [];
$newPhpFiles = array_values(array_diff($phpAfter, $phpBefore));
$exitCode = 0;

if (empty($newPhpFiles)) {
    echo itm_script_format_status_line('[PASS] SUCCESS: Malicious .php file upload was rejected.') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] FAILURE: Malicious .php file was uploaded!') . $nl;
    $exitCode = 1;
    foreach ($newPhpFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

itm_repro_floor_designer_cleanup_plan(
    $conn,
    $company_id,
    $planId,
    $uploadDir,
    $storedFilename,
    (int)$testUser['id']
);

itm_script_output_end();
exit($exitCode);
