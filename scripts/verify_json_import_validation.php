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
$_SESSION['user_id'] = 1;
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
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] Expected ok:false with zero inserts; result: ' . json_encode($result), 'fail') . $nl;
itm_script_output_end();
exit(1);
