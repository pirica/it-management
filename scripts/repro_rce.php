<?php
/**
 * PoC for RCE in Floor Designer via 'save_as_floor_plan' action.
 * Why: Patched handler coerces ext=php to png; absence of .php on disk is [PASS], not an error.
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
require_once __DIR__ . '/../modules/floor_plans/gallery_helpers.php';
require_once __DIR__ . '/lib/itm_repro_floor_designer_rce.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-floor-rce']);
if (!is_array($testUser)) {
    die("Failed to create test user\n");
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: Floor Designer RCE');
$nl = itm_script_output_nl();

echo colorText('Testing RCE via File Upload in modules/floor_designer/index.php', 'info') . $nl;

$sampleDataUri = itm_repro_floor_designer_sample_png_data_uri();
if ($sampleDataUri === '') {
    echo itm_script_format_status_line('[FAIL] Sample PNG missing under images/switch_port_icons/.') . $nl;
    itm_script_output_end();
    exit(1);
}

$uploadDir = fp_company_upload_dir($company_id);
$phpBefore = glob($uploadDir . 'floor_plan_*.php') ?: [];
$testName = 'RCE Test ' . str_replace('.', '', (string)microtime(true));

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
    if ($output !== '') {
        echo 'Response preview: ' . substr($output, 0, 200) . $nl;
    }
    if (stripos($output, 'Status: 302') !== false) {
        echo 'Hint: browser runs must spawn Laragon CLI php.exe (not php-cgi) for the isolated subprocess.' . $nl;
    }
    itm_script_output_end();
    exit(1);
}

$planId = (int)($decoded['id'] ?? 0);
$storedFilename = '';
$fileExt = '';
if ($planId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT stored_filename, file_ext FROM floor_plans WHERE id = ? AND company_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $planId, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (is_array($row)) {
        $storedFilename = (string)($row['stored_filename'] ?? '');
        $fileExt = strtolower((string)($row['file_ext'] ?? ''));
    }
}

$phpAfter = glob($uploadDir . 'floor_plan_*.php') ?: [];
$newPhpFiles = array_values(array_diff($phpAfter, $phpBefore));
$exitCode = 0;
$phpBin = itm_repro_floor_designer_resolve_php_binary();

if (!empty($newPhpFiles)) {
    $uploadedFile = $newPhpFiles[0];
    echo 'Found uploaded .php file: ' . $uploadedFile . $nl;
    $executionOutput = shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($uploadedFile));
    if (is_string($executionOutput) && strpos($executionOutput, 'RCE_SUCCESS') !== false) {
        echo itm_script_format_status_line('[FAIL] VULNERABILITY CONFIRMED: Remote Code Execution successful.') . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] Malicious .php file was written to the upload directory.') . $nl;
    }
    $exitCode = 1;
} elseif (!empty($decoded['ok'])) {
    echo 'Upload accepted; stored extension: ' . ($fileExt !== '' ? $fileExt : 'unknown') . $nl;
    if ($fileExt === 'php') {
        echo itm_script_format_status_line('[FAIL] Database recorded file_ext=php despite upload hardening.') . $nl;
        $exitCode = 1;
    } elseif ($storedFilename !== '' && substr($storedFilename, -4) === '.php') {
        echo itm_script_format_status_line('[FAIL] Stored filename ends with .php.') . $nl;
        $exitCode = 1;
    } else {
        echo itm_script_format_status_line('[PASS] Malicious .php upload was rejected; file saved with safe extension.') . $nl;
    }
} else {
    $error = (string)($decoded['error'] ?? 'unknown error');
    echo itm_script_format_status_line('[PASS] Upload rejected: ' . $error) . $nl;
}

itm_repro_floor_designer_cleanup_plan(
    $conn,
    $company_id,
    $planId,
    $uploadDir,
    $storedFilename,
    (int)$testUser['id']
);

foreach ($newPhpFiles as $phpFile) {
    if (is_file($phpFile)) {
        @unlink($phpFile);
    }
}

itm_script_output_end();
exit($exitCode);
