<?php
/**
 * Repro: Data loss in generic table import when columns are missing from payload.
 */
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
$root = dirname(__DIR__);

session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;

require_once $root . '/config/config.php';

$companyId = 1;

echo "--- Repro: Generic Table Import Data Loss ---\n";

/* CSRF-SCAN-EXCLUDE */
/* SQL-INJECTION-SCAN-EXCLUDE */

// 1. Seed a department with a code
mysqli_query($conn, "DELETE FROM departments WHERE name = 'Repro Dept'");
$code = 'RD1';
$cid_sql = (int)$companyId;
$code_sql = mysqli_real_escape_string($conn, $code);
$res = mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES ($cid_sql, 'Repro Dept', '$code_sql', 1)");
if (!$res) echo "Seed failed: " . mysqli_error($conn) . "\n";

$res = mysqli_query($conn, "SELECT id, code FROM departments WHERE name = 'Repro Dept'");
$dept = mysqli_fetch_assoc($res);
$id = $dept['id'];
echo "Initial department code: " . ($dept['code'] ?? 'NULL') . "\n";

// 2. Run generic import with ID and Name but WITHOUT code
$csrf = itm_get_csrf_token();
$importData = [
    'csrf_token' => $csrf,
    'import_excel_rows' => [
        ['id', 'name'],
        [$id, 'Repro Dept']
    ]
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

echo "Running generic import WITHOUT code column...\n";
ob_start();
$result = itm_handle_json_table_import($conn, 'departments', $companyId, $importData, true);
$resJson = ob_get_clean();
echo "Import Result: " . json_encode($result) . "\n";

// 3. Check if code is still there
$id_sql = (int)$id;
$res = mysqli_query($conn, "SELECT code FROM departments WHERE id = $id_sql");
$dept = mysqli_fetch_assoc($res);
$finalCode = $dept['code'] ?? 'NULL';
echo "Final department code: " . $finalCode . "\n";

if ($finalCode === 'NULL' || $finalCode === '' || $finalCode !== $code) {
    echo "BUG CONFIRMED: Missing column in generic import caused data loss (code was wiped).\n";
} else {
    echo "SUCCESS: Data preserved.\n";
}
