<?php
/**
 * Repro: Destructive Employee Import
 *
 * Construct a scenario where an import deletes existing employees.
 *
 * Browser + CLI. Default dry-run; --apply / ?apply=1 (Admin) seeds disposable rows,
 * runs import, asserts rows missing from payload survive, then tears down disposable rows.
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
require_once __DIR__ . '/lib/itm_script_test_employee.php';

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

/**
 * @return bool
 */
function itm_repro_destructive_import_employee_live($conn, $employeeId, $companyId)
{
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $employeeId = (int)$employeeId;
    $companyId = (int)$companyId;
    if ($employeeId <= 0 || $companyId <= 0) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return is_array($row);
}

/**
 * @return never
 */
function itm_repro_destructive_import_fail($message, $nl, $conn = null, array $employeeIds = [])
{
    if ($conn instanceof mysqli) {
        foreach ($employeeIds as $employeeId) {
            $employeeId = (int)$employeeId;
            if ($employeeId > 0) {
                itm_script_test_employee_delete($conn, $employeeId);
            }
        }
    }

    echo colorText((string)$message, 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText(
    $apply
        ? 'Mode: APPLY (disposable employee rows for company ' . $companyId . ')'
        : 'Mode: DRY-RUN (default — no database writes)',
    $apply ? 'fail' : 'info'
) . $nl;

echo '--- Repro: Destructive Employee Import ---' . $nl;

$currentCount = 0;
$stmtCount = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS c FROM employees WHERE company_id = ? AND deleted_at IS NULL'
);
if ($stmtCount) {
    mysqli_stmt_bind_param($stmtCount, 'i', $companyId);
    mysqli_stmt_execute($stmtCount);
    $countRes = mysqli_stmt_get_result($stmtCount);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $currentCount = (int)($countRow['c'] ?? 0);
    }
}

echo 'Current live employee count for company ' . $companyId . ': ' . $currentCount . $nl;

itm_apply_script_echo_list('Planned steps when apply is enabled', [
    'INSERT two disposable employees (Keep Me + Delete Me) via itm_script_test_employee_create()',
    'POST modules/employees/index.php action=import_employees with payload containing only Keep Me',
    'Assert Delete Me row still exists (id + work_email, deleted_at IS NULL)',
    'Teardown disposable rows via itm_script_test_employee_delete()',
    'Report [PASS] when Delete Me survives import; [FAIL] when it is removed or soft-deleted',
]);

if (!$apply) {
    if ($itmIsCli) {
        echo 'Re-run with --apply to execute: php scripts/repro_destructive_import.php --apply' . $nl;
    } else {
        echo 'Open with ?apply=1 to execute (Admin): repro_destructive_import.php?apply=1' . $nl;
    }
    if ($stmtCount) {
        mysqli_stmt_close($stmtCount);
    }
    itm_script_output_end();
    exit(0);
}

/* CSRF-SCAN-EXCLUDE */

$runToken = bin2hex(random_bytes(4));
$keepEmail = 'repro-keep-' . $runToken . '@script-test.example.com';
$deleteEmail = 'repro-delete-' . $runToken . '@script-test.example.com';

$keepEmployee = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'repro-destructive-import-keep',
    'first_name' => 'Keep',
    'last_name' => 'Me',
    'email' => $keepEmail,
]);
if (!is_array($keepEmployee)) {
    itm_repro_destructive_import_fail('[FAIL] Unable to seed disposable Keep Me employee.', $nl);
}

$deleteEmployee = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'repro-destructive-import-delete',
    'first_name' => 'Delete',
    'last_name' => 'Me',
    'email' => $deleteEmail,
]);
if (!is_array($deleteEmployee)) {
    itm_repro_destructive_import_fail(
        '[FAIL] Unable to seed disposable Delete Me employee.',
        $nl,
        $conn,
        [(int)$keepEmployee['id']]
    );
}

$keepId = (int)$keepEmployee['id'];
$deleteId = (int)$deleteEmployee['id'];
$disposableIds = [$keepId, $deleteId];

itm_script_test_employee_register_teardown($conn, $keepId, []);
itm_script_test_employee_register_teardown($conn, $deleteId, []);

echo 'Seeded disposable employees for company ' . $companyId . ':' . $nl;
echo '  Keep Me id=' . $keepId . ' email=' . $keepEmail . $nl;
echo '  Delete Me id=' . $deleteId . ' email=' . $deleteEmail . $nl;

$_SESSION['company_id'] = $companyId;
if ((int)($_SESSION['employee_id'] ?? 0) <= 0) {
    $_SESSION['employee_id'] = 1;
}
$_SESSION['csrf_token'] = 'repro_token';

$_POST['csrf_token'] = 'repro_token';
$_POST['action'] = 'import_employees';
$_POST['import_payload'] = json_encode(
    [
        ['First Name', 'Last Name', 'Work Email'],
        ['Keep', 'Me', $keepEmail],
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

$keepLive = itm_repro_destructive_import_employee_live($conn, $keepId, $companyId);
$deleteLive = itm_repro_destructive_import_employee_live($conn, $deleteId, $companyId);

echo 'After import — Keep Me live: ' . ($keepLive ? 'yes' : 'no') . $nl;
echo 'After import — Delete Me live: ' . ($deleteLive ? 'yes' : 'no') . $nl;

$exitCode = 0;
if (!$deleteLive) {
    echo colorText('[FAIL] Import deleted or soft-deleted Delete Me (not in payload).', 'fail') . $nl;
    $exitCode = 1;
} elseif (!$keepLive) {
    echo colorText('[FAIL] Import removed Keep Me even though it was in the payload.', 'fail') . $nl;
    $exitCode = 1;
} else {
    echo colorText('[PASS] No records deleted during import.', 'pass') . $nl;
}

foreach ($disposableIds as $employeeId) {
    itm_script_test_employee_delete($conn, $employeeId);
}

if ($stmtCount) {
    mysqli_stmt_close($stmtCount);
}

itm_script_output_end();
exit($exitCode);
