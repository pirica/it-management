<?php
/**
 * Verification script for Employees Clear Table soft-delete.
 *
 * Why: Proves clear-table soft-deletes the employee (audit row remains) and
 * detaches safe inbound links such as bookmarks.
 */

define('ITM_CLI_SCRIPT', true);
// Why: Robust path resolution that works across different deployment layouts.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employees Clear Table Soft-Delete Verification');
$nl = itm_script_output_nl();

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$_SESSION['employee_id'] = 1;

mysqli_query($conn, 'SET @app_company_id = 1');
mysqli_query($conn, 'SET @app_employee_id = 1');
mysqli_query($conn, "SET @app_username = 'cli-test'");
mysqli_query($conn, "SET @app_email = 'cli-test@example.com'");
mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
mysqli_query($conn, "SET @app_user_agent = 'verify_clear_table_fix'");

// 1. Setup: Create a test company
echo "Setting up test environment..." . $nl;
$testCompanyName = 'Clear Table Test Co ' . bin2hex(random_bytes(4));
$stmtCo = mysqli_prepare($conn, "INSERT INTO companies (company, active) VALUES (?, 1)");
mysqli_stmt_bind_param($stmtCo, 's', $testCompanyName);
if (!mysqli_stmt_execute($stmtCo)) {
    echo colorText('[FAIL] Company insert failed: ' . mysqli_error($conn), 'fail') . $nl;
    mysqli_stmt_close($stmtCo);
    itm_script_output_end();
    exit(1);
}
$testCompanyId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtCo);
if ($testCompanyId <= 0) {
    echo colorText('[FAIL] Company insert returned id 0', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
echo "Created test company ID: $testCompanyId" . $nl;

mysqli_query($conn, 'SET @app_company_id = ' . $testCompanyId);

// Seed a status for this tenant (employment_status_id FK)
$stmtSt = mysqli_prepare($conn, "INSERT INTO employee_statuses (company_id, name, active) VALUES (?, 'Active', 1)");
mysqli_stmt_bind_param($stmtSt, 'i', $testCompanyId);
mysqli_stmt_execute($stmtSt);
$statusId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtSt);

// 2. Setup: Create a test employee
$testUsername = 'test_clear_' . bin2hex(random_bytes(4));
$stmtEmp = mysqli_prepare($conn, "INSERT INTO employees (company_id, first_name, last_name, username, employment_status_id) VALUES (?, 'Test', 'Clear', ?, ?)");
mysqli_stmt_bind_param($stmtEmp, 'isi', $testCompanyId, $testUsername, $statusId);
if (!mysqli_stmt_execute($stmtEmp)) {
    echo colorText('[FAIL] Employee insert failed: ' . mysqli_error($conn), 'fail') . $nl;
    mysqli_stmt_close($stmtEmp);
    mysqli_query($conn, "DELETE FROM employee_statuses WHERE company_id = $testCompanyId");
    mysqli_query($conn, "DELETE FROM companies WHERE id = $testCompanyId");
    itm_script_output_end();
    exit(1);
}
$testEmployeeId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtEmp);
echo "Created test employee ID: $testEmployeeId ($testUsername)" . $nl;

// 3. Setup: Create a dependency (Bookmark)
$stmtBk = mysqli_prepare($conn, "INSERT INTO bookmarks (company_id, employee_id, title, url) VALUES (?, ?, 'Test Link', 'https://example.com')");
mysqli_stmt_bind_param($stmtBk, 'ii', $testCompanyId, $testEmployeeId);
mysqli_stmt_execute($stmtBk);
$testBookmarkId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtBk);
echo "Created dependent bookmark ID: $testBookmarkId" . $nl;

require_once ROOT_PATH . 'modules/employees/delete_functions.php';
require_once ROOT_PATH . 'modules/employees/delete_clear_table.php';

echo "Executing clear-table soft-delete..." . $nl;
$error = employees_clear_table_for_company($conn, $testCompanyId);

$exitCode = 0;
if ($error !== null) {
    echo colorText("[FAIL] Clear table failed with error: $error", 'fail') . $nl;
    $exitCode = 1;
} else {
    $empCheck = mysqli_query($conn, "SELECT active, deleted_at FROM employees WHERE id = $testEmployeeId");
    $empRow = $empCheck ? mysqli_fetch_assoc($empCheck) : null;
    $bkCheck = mysqli_query($conn, "SELECT id FROM bookmarks WHERE id = $testBookmarkId");
    $bkExists = $bkCheck && mysqli_num_rows($bkCheck) > 0;

    $softDeleted = is_array($empRow)
        && (int)($empRow['active'] ?? 1) === 0
        && !empty($empRow['deleted_at']);

    if ($softDeleted && !$bkExists) {
        echo colorText("[PASS] Clear table soft-deleted employee and detached bookmark.", 'pass') . $nl;
    } else {
        $exitCode = 1;
        if (!$softDeleted) {
            echo colorText("[FAIL] Employee was not soft-deleted (active=0 + deleted_at).", 'fail') . $nl;
        }
        if ($bkExists) {
            echo colorText("[FAIL] Bookmark dependency still exists.", 'fail') . $nl;
        }
    }
}

echo "Cleaning up..." . $nl;
mysqli_query($conn, "DELETE FROM bookmarks WHERE company_id = $testCompanyId");
mysqli_query($conn, "DELETE FROM employees WHERE company_id = $testCompanyId");
mysqli_query($conn, "DELETE FROM employee_statuses WHERE company_id = $testCompanyId");
mysqli_query($conn, "DELETE FROM companies WHERE id = $testCompanyId");

// Why: Remove leftover duplicate from older fixed-name runs.
mysqli_query($conn, "DELETE FROM employees WHERE company_id IN (SELECT id FROM (SELECT id FROM companies WHERE company = 'Test Company Bespoke Employees') t)");
mysqli_query($conn, "DELETE FROM companies WHERE company = 'Test Company Bespoke Employees'");

itm_script_output_end();
exit($exitCode);
