<?php
/**
 * Verifies JSON import rejects invalid numeric column values instead of silent NULL inserts.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('JSON Import Validation Verification');

$nl = itm_script_output_nl();
echo 'Verifying JSON import rejects invalid decimal values...' . $nl;

$companyId = 1;
$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = 1;
$_SESSION['csrf_token'] = 'test_token';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$uniqueModel = 'ImportValidation-' . bin2hex(random_bytes(4));
$payload = [
    'csrf_token' => 'test_token',
    'import_excel_rows' => [
        ['Model', 'Price'],
        [$uniqueModel, 'invalid-price'],
    ],
];

$result = itm_handle_json_table_import($conn, 'catalogs', $companyId, $payload, true);
$ok = is_array($result)
    && ($result['ok'] ?? true) === false
    && (int)($result['inserted'] ?? -1) === 0
    && (int)($result['failed'] ?? 0) >= 1;

$check = mysqli_prepare($conn, 'SELECT id FROM catalogs WHERE company_id = ? AND model = ? LIMIT 1');
mysqli_stmt_bind_param($check, 'is', $companyId, $uniqueModel);
mysqli_stmt_execute($check);
$res = mysqli_stmt_get_result($check);
$rowInserted = $res && mysqli_num_rows($res) > 0;
mysqli_stmt_close($check);

if ($ok && !$rowInserted) {
    echo colorText('[PASS] Invalid price rejected; no catalog row inserted.', 'pass') . $nl;
} else {
    echo colorText('[FAIL] Expected ok:false with zero inserts; result: ' . json_encode($result), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo 'Verifying JSON import rejects invalid datetime values...' . $nl;

$uniqueTitle = 'ImportValidationDate-' . bin2hex(random_bytes(4));
$datePayload = [
    'csrf_token' => 'test_token',
    'import_excel_rows' => [
        ['Title', 'Start Datetime'],
        [$uniqueTitle, 'not-a-date'],
    ],
];

$dateResult = itm_handle_json_table_import($conn, 'events', $companyId, $datePayload, true);
$dateOk = is_array($dateResult)
    && ($dateResult['ok'] ?? true) === false
    && (int)($dateResult['inserted'] ?? -1) === 0
    && (int)($dateResult['failed'] ?? 0) >= 1;

$dateCheck = mysqli_prepare($conn, 'SELECT id FROM events WHERE company_id = ? AND title = ? LIMIT 1');
mysqli_stmt_bind_param($dateCheck, 'is', $companyId, $uniqueTitle);
mysqli_stmt_execute($dateCheck);
$dateRes = mysqli_stmt_get_result($dateCheck);
$dateRowInserted = $dateRes && mysqli_num_rows($dateRes) > 0;
mysqli_stmt_close($dateCheck);

if ($dateOk && !$dateRowInserted) {
    echo colorText('[PASS] Invalid datetime rejected; no event row inserted.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] Expected ok:false for invalid datetime; result: ' . json_encode($dateResult), 'fail') . $nl;
itm_script_output_end();
exit(1);
