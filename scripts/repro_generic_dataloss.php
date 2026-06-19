<?php
/**
 * Repro: Data loss in generic table import when columns are missing from payload.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
$root = dirname(__DIR__);

session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;

require_once $root . '/config/config.php';

$companyId = 1;
$exitCode = 0;

echo "--- Repro: Generic Table Import Data Loss ---\n";

/* CSRF-SCAN-EXCLUDE */
/* SQL-INJECTION-SCAN-EXCLUDE */

mysqli_begin_transaction($conn);

try {
    if (!mysqli_query($conn, "DELETE FROM departments WHERE name = 'Repro Dept' AND company_id = " . (int)$companyId)) {
        throw new RuntimeException('Seed delete failed: ' . mysqli_error($conn));
    }

    $code = 'RD1';
    $cidSql = (int)$companyId;
    $codeSql = mysqli_real_escape_string($conn, $code);
    if (!mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES ($cidSql, 'Repro Dept', '$codeSql', 1)")) {
        throw new RuntimeException('Seed insert failed: ' . mysqli_error($conn));
    }

    $res = mysqli_query($conn, "SELECT id, code FROM departments WHERE name = 'Repro Dept' AND company_id = $cidSql LIMIT 1");
    if (!$res || mysqli_num_rows($res) !== 1) {
        throw new RuntimeException('Seed row not found after insert.');
    }
    $dept = mysqli_fetch_assoc($res);
    $id = (int)($dept['id'] ?? 0);
    echo "Initial department code: " . ($dept['code'] ?? 'NULL') . "\n";

    $csrf = itm_get_csrf_token();
    $importData = [
        'csrf_token' => $csrf,
        'import_excel_rows' => [
            ['id', 'name'],
            [$id, 'Repro Dept'],
        ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    echo "Running generic import WITHOUT code column...\n";
    ob_start();
    $result = itm_handle_json_table_import($conn, 'departments', $companyId, $importData, true);
    ob_end_clean();

    if (!$result || empty($result['ok']) || !empty($result['failed'])) {
        throw new RuntimeException('Import failed: ' . json_encode($result));
    }

    echo "Import result: " . json_encode($result) . "\n";

    $idSql = (int)$id;
    $res = mysqli_query($conn, "SELECT code FROM departments WHERE id = $idSql LIMIT 1");
    if (!$res || mysqli_num_rows($res) !== 1) {
        throw new RuntimeException('Department row missing after import.');
    }
    $dept = mysqli_fetch_assoc($res);
    $finalCode = $dept['code'] ?? 'NULL';
    echo "Final department code: " . $finalCode . "\n";

    if ($finalCode === 'NULL' || $finalCode === '' || $finalCode !== $code) {
        throw new RuntimeException('BUG CONFIRMED: missing column in generic import caused data loss (code was wiped).');
    }

    echo "SUCCESS: Data preserved.\n";
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    fwrite(STDERR, "Repro failed: " . $e->getMessage() . "\n");
    $exitCode = 1;
}

exit($exitCode);
