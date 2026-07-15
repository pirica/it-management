<?php
/**
 * Verification script for Employees Clear Table Fix.
 *
 * Why: This tool proves that the iterative clear-table logic successfully
 * navigates Foreign Key dependencies by creating a test employee with
 * linked records and confirming their complete removal.
 */

define('ITM_CLI_SCRIPT', true);
// Why: Robust path resolution that works across different deployment layouts.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employees Clear Table Fix Verification');
$nl = itm_script_output_nl();

// 1. Setup: Create a test company
echo "Setting up test environment..." . $nl;
$testCompanyName = 'Clear Table Test Co ' . bin2hex(random_bytes(4));
$stmtCo = mysqli_prepare($conn, "INSERT INTO companies (company, active) VALUES (?, 1)");
mysqli_stmt_bind_param($stmtCo, 's', $testCompanyName);
mysqli_stmt_execute($stmtCo);
$testCompanyId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtCo);
echo "Created test company ID: $testCompanyId" . $nl;

// 2. Setup: Create a test employee
$testUsername = 'test_clear_' . bin2hex(random_bytes(4));
$stmtEmp = mysqli_prepare($conn, "INSERT INTO employees (company_id, first_name, last_name, username, employment_status_id) VALUES (?, 'Test', 'Clear', ?, 1)");
mysqli_stmt_bind_param($stmtEmp, 'is', $testCompanyId, $testUsername);
mysqli_stmt_execute($stmtEmp);
$testEmployeeId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtEmp);
echo "Created test employee ID: $testEmployeeId ($testUsername)" . $nl;

// 3. Setup: Create a dependency (Bookmark)
// Why: Bookmarks are a standard inbound reference that would normally block a bulk DELETE.
$stmtBk = mysqli_prepare($conn, "INSERT INTO bookmarks (company_id, employee_id, title, url) VALUES (?, ?, 'Test Link', 'https://example.com')");
mysqli_stmt_bind_param($stmtBk, 'ii', $testCompanyId, $testEmployeeId);
mysqli_stmt_execute($stmtBk);
$testBookmarkId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmtBk);
echo "Created dependent bookmark ID: $testBookmarkId" . $nl;

// 4. Load the fix and dependencies
require_once ROOT_PATH . 'modules/employees/delete_functions.php';
require_once ROOT_PATH . 'modules/employees/delete_clear_table.php';

// 5. Execute the Clear Table action
echo "Executing fixed clear-table logic..." . $nl;
$error = employees_clear_table_for_company($conn, $testCompanyId);

if ($error !== null) {
    echo colorText("[FAIL] Clear table failed with error: $error", 'fail') . $nl;
} else {
    // 6. Verify results
    $empCheck = mysqli_query($conn, "SELECT id FROM employees WHERE id = $testEmployeeId");
    $bkCheck = mysqli_query($conn, "SELECT id FROM bookmarks WHERE id = $testBookmarkId");

    $empExists = mysqli_num_rows($empCheck) > 0;
    $bkExists = mysqli_num_rows($bkCheck) > 0;

    if (!$empExists && !$bkExists) {
        echo colorText("[PASS] Clear table succeeded! Employee and dependent bookmark were removed.", 'pass') . $nl;
    } else {
        if ($empExists) echo colorText("[FAIL] Employee record still exists.", 'fail') . $nl;
        if ($bkExists) echo colorText("[FAIL] Bookmark dependency still exists.", 'fail') . $nl;
    }
}

// 7. Teardown
echo "Cleaning up..." . $nl;
// Why: Cleanup the test company. Inbound references should have been removed by the fix.
mysqli_query($conn, "DELETE FROM companies WHERE id = $testCompanyId");

itm_script_output_end();
