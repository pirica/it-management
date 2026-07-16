<?php
/**
 * Repro: Destructive Employee Import
 *
 * Construct a scenario where an import deletes existing employees.
 *
 * Browser + CLI. Default dry-run; --apply / ?apply=1 (Admin) runs the disposable DB scenario.
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
    require_once __DIR__ . '/../config/config.php';
} else {
    require_once __DIR__ . '/../config/config.php';
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    if (!function_exists('itm_is_admin') || !itm_is_admin($conn, $employeeId)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden: administrator login required.\n";
        exit(1);
    }
}

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

itm_script_output_begin('Repro: Destructive Employee Import');

$nl = itm_script_output_nl();
$root = dirname(__DIR__) . '/';
$companyId = 1;

$apply = false;
if ($itmIsCli) {
    $apply = in_array('--apply', $argv ?? [], true);
} else {
    $apply = isset($_GET['apply']) && (string)$_GET['apply'] === '1';
}

echo colorText(
    $apply
        ? 'Mode: APPLY (destructive database writes for company ' . $companyId . ')'
        : 'Mode: DRY-RUN (default — no database writes)',
    $apply ? 'fail' : 'info'
) . $nl;

echo '--- Repro: Destructive Employee Import ---' . $nl;

$currentCount = 0;
$stmtCount = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM employees WHERE company_id = ?');
if ($stmtCount) {
    mysqli_stmt_bind_param($stmtCount, 'i', $companyId);
    mysqli_stmt_execute($stmtCount);
    $countRes = mysqli_stmt_get_result($stmtCount);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $currentCount = (int)($countRow['c'] ?? 0);
    }
}

echo 'Current employee count for company ' . $companyId . ': ' . $currentCount . $nl;

itm_apply_script_echo_list('Planned steps when apply is enabled', [
    'DELETE FROM employees WHERE company_id = ' . $companyId,
    'INSERT seed rows: Keep Me (keep@example.com), Delete Me (delete@example.com)',
    'POST modules/employees/index.php action=import_employees with payload containing only Keep Me',
    'Report [PASS] when final count >= seeded count; [FAIL] when import deletes rows missing from payload',
]);

if (!$apply) {
    if ($itmIsCli) {
        echo 'Re-run with --apply to execute: php scripts/repro_destructive_import.php --apply' . $nl;
    } else {
        echo 'Open with ?apply=1 to execute (Admin): repro_destructive_import.php?apply=1' . $nl;
    }
    itm_script_output_end();
    exit(0);
}

/* CSRF-SCAN-EXCLUDE */

$_SESSION['company_id'] = $companyId;
if ((int)($_SESSION['employee_id'] ?? 0) <= 0) {
    $_SESSION['employee_id'] = 1;
}
$_SESSION['csrf_token'] = 'repro_token';

$stmtDel = mysqli_prepare($conn, 'DELETE FROM employees WHERE company_id = ?');
if (!$stmtDel) {
    echo colorText('[FAIL] Unable to prepare seed cleanup DELETE.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($stmtDel, 'i', $companyId);
mysqli_stmt_execute($stmtDel);
mysqli_stmt_close($stmtDel);

$stmtIns1 = mysqli_prepare(
    $conn,
    "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate)
     VALUES (?, 'Keep', 'Me', 'Keep Me', 'keep@example.com', 1, 0)"
);
if ($stmtIns1) {
    mysqli_stmt_bind_param($stmtIns1, 'i', $companyId);
    mysqli_stmt_execute($stmtIns1);
    mysqli_stmt_close($stmtIns1);
}

$stmtIns2 = mysqli_prepare(
    $conn,
    "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate)
     VALUES (?, 'Delete', 'Me', 'Delete Me', 'delete@example.com', 1, 0)"
);
if ($stmtIns2) {
    mysqli_stmt_bind_param($stmtIns2, 'i', $companyId);
    mysqli_stmt_execute($stmtIns2);
    mysqli_stmt_close($stmtIns2);
}

if (!$stmtCount) {
    echo colorText('[FAIL] Unable to prepare employee count query.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

mysqli_stmt_bind_param($stmtCount, 'i', $companyId);
mysqli_stmt_execute($stmtCount);
$initialCountRes = mysqli_stmt_get_result($stmtCount);
$initialCount = (int)(mysqli_fetch_assoc($initialCountRes)['c'] ?? 0);
echo 'Seeded employee count for company ' . $companyId . ': ' . $initialCount . $nl;

$_POST['csrf_token'] = 'repro_token';
$_POST['action'] = 'import_employees';
$_POST['import_payload'] = json_encode(
    [
        ['First Name', 'Last Name', 'Work Email'],
        ['Keep', 'Me', 'keep@example.com'],
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
$_SERVER['REQUEST_METHOD'] = 'POST';

echo 'Running import with 1 employee (Keep Me)...' . $nl;
chdir($root . 'modules/employees');
ob_start();
require 'index.php';
ob_end_clean();
chdir($root);

mysqli_stmt_bind_param($stmtCount, 'i', $companyId);
mysqli_stmt_execute($stmtCount);
$finalCountRes = mysqli_stmt_get_result($stmtCount);
$finalCount = (int)(mysqli_fetch_assoc($finalCountRes)['c'] ?? 0);
mysqli_stmt_close($stmtCount);

echo 'Final employee count for company ' . $companyId . ': ' . $finalCount . $nl;

$exitCode = 0;
if ($finalCount < $initialCount) {
    echo colorText('[FAIL] Import deleted existing records not in payload.', 'fail') . $nl;
    $exitCode = 1;
} else {
    echo colorText('[PASS] No records deleted during import.', 'pass') . $nl;
}

itm_script_output_end();
exit($exitCode);
