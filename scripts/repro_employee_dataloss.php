<?php
/**
 * Repro: Data loss in employee JSON import when columns are missing from payload.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Repro: Employee Import Data Loss');

$root = dirname(__DIR__);
$nl = itm_script_output_nl();

$companyId = 1;
$exitCode = 0;

echo "--- Repro: Employee Import Data Loss ---" . $nl;

/* CSRF-SCAN-EXCLUDE */
/* SQL-INJECTION-SCAN-EXCLUDE */

mysqli_begin_transaction($conn);

try {
    if (!mysqli_query($conn, "DELETE FROM employees WHERE work_email = 'repro@example.com'")) {
        throw new RuntimeException('Seed delete failed: ' . mysqli_error($conn));
    }

    $mobile = '123-456-7890';
    $cidSql = (int)$companyId;
    $mobileSql = mysqli_real_escape_string($conn, $mobile);
    $insertSql = "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, mobile_phone, employment_status_id)
                  VALUES ($cidSql, 'Repro', 'User', 'Repro User', 'repro@example.com', '$mobileSql', 1)";
    if (!mysqli_query($conn, $insertSql)) {
        throw new RuntimeException('Seed insert failed: ' . mysqli_error($conn));
    }

    $res = mysqli_query($conn, "SELECT id, mobile_phone FROM employees WHERE work_email = 'repro@example.com' LIMIT 1");
    if (!$res || mysqli_num_rows($res) !== 1) {
        throw new RuntimeException('Seed row not found after insert.');
    }
    $employee = mysqli_fetch_assoc($res);
    $employeeId = (int)($employee['id'] ?? 0);
    echo "Initial mobile phone: " . ($employee['mobile_phone'] ?? 'NULL') . "\n";

    $csrf = itm_get_csrf_token();
    $importData = [
        'csrf_token' => $csrf,
        'import_excel_rows' => [
            ['id', 'First Name', 'Last Name', 'Work Email'],
            [$employeeId, 'Repro', 'User', 'repro@example.com'],
        ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    echo "Running generic employees import WITHOUT mobile phone column...\n";
    ob_start();
    $result = itm_handle_json_table_import($conn, 'employees', $companyId, $importData, true);
    ob_end_clean();

    if (!$result || empty($result['ok']) || !empty($result['failed'])) {
        throw new RuntimeException('Import failed: ' . json_encode($result));
    }

    echo "Import result: " . json_encode($result) . "\n";

    $idSql = (int)$employeeId;
    $res = mysqli_query($conn, "SELECT mobile_phone FROM employees WHERE id = $idSql LIMIT 1");
    if (!$res || mysqli_num_rows($res) !== 1) {
        throw new RuntimeException('Employee row missing after import.');
    }
    $employee = mysqli_fetch_assoc($res);
    $finalMobile = $employee['mobile_phone'] ?? 'NULL';
    echo "Final mobile phone: " . $finalMobile . "\n";

    if ($finalMobile === 'NULL' || $finalMobile === '') {
        throw new RuntimeException('BUG CONFIRMED: missing column in import caused data loss (mobile_phone was wiped).');
    }

    echo colorText("SUCCESS: Data preserved.", 'pass') . $nl;
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo colorText("Repro failed: " . $e->getMessage(), 'fail') . $nl;
    $exitCode = 1;
}

itm_script_output_end();
exit($exitCode);
