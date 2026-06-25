<?php
/**
 * Reproduction script for incorrect UNIQUE constraints and trigger errors in database.sql.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

function test_expenses($conn) {
    echo "Testing expenses table unique constraint...\n";
    $company_id = 1;
    $cost_center_id = 1;
    $gl_account_id = 1;
    $date1 = '2026-01-01';
    $date2 = '2026-01-02';
    
    // Clear existing for clean test
    mysqli_query($conn, "DELETE FROM expenses WHERE company_id = $company_id AND cost_center_id = $cost_center_id");
    
    $sql1 = "INSERT INTO expenses (company_id, cost_center_id, gl_account_id, `date`, amount, active) VALUES ($company_id, $cost_center_id, $gl_account_id, '$date1', 100.00, 1)";
    if (!mysqli_query($conn, $sql1)) {
        echo "[FAIL] First expense insert failed: " . mysqli_error($conn) . "\n";
        return false;
    }
    echo "[PASS] First expense insert successful.\n";
    
    $sql2 = "INSERT INTO expenses (company_id, cost_center_id, gl_account_id, `date`, amount, active) VALUES ($company_id, $cost_center_id, $gl_account_id, '$date2', 200.00, 1)";
    if (!mysqli_query($conn, $sql2)) {
        echo "[EXPECTED FAIL] Second expense insert failed (as predicted): " . mysqli_error($conn) . "\n";
    } else {
        echo "[SUCCESS] Second expense insert successful. The constraint issue is NOT present.\n";
    }
    return true;
}

function test_assignment_history($conn) {
    echo "\nTesting employee_assignment_history table unique constraint...\n";
    $company_id = 1;
    
    // Get a valid employee_id
    $res = mysqli_query($conn, "SELECT id FROM employees WHERE company_id = $company_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        echo "[SKIP] No employee found for company $company_id.\n";
        return false;
    }
    $employee_id = $row['id'];
    $date1 = '2026-01-01';
    $date2 = '2026-01-02';
    
    // Clear existing for clean test
    mysqli_query($conn, "DELETE FROM employee_assignment_history WHERE company_id = $company_id AND employee_id = $employee_id");
    
    $sql1 = "INSERT INTO employee_assignment_history (company_id, employee_id, assigned_date, active) VALUES ($company_id, $employee_id, '$date1', 1)";
    if (!mysqli_query($conn, $sql1)) {
        echo "[FAIL] First assignment insert failed: " . mysqli_error($conn) . "\n";
        return false;
    }
    echo "[PASS] First assignment insert successful.\n";
    
    $sql2 = "INSERT INTO employee_assignment_history (company_id, employee_id, assigned_date, active) VALUES ($company_id, $employee_id, '$date2', 1)";
    if (!mysqli_query($conn, $sql2)) {
        echo "[EXPECTED FAIL] Second assignment insert failed (as predicted): " . mysqli_error($conn) . "\n";
    } else {
        echo "[SUCCESS] Second assignment insert successful. The constraint issue is NOT present.\n";
    }
    return true;
}

function test_employee_trigger($conn) {
    echo "\nTesting add_default_bookmarks_for_new_admin trigger...\n";
    $company_id = 1;
    $username = 'test_admin_' . time();
    
    // Get a valid employment_status_id
    $res = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE company_id = $company_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    $status_id = $row['id'] ?? 1;

    // Get Admin role id
    $res = mysqli_query($conn, "SELECT id FROM employee_roles WHERE company_id = $company_id AND LOWER(name) = 'admin' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    $role_id = $row['id'] ?? null;

    if (!$role_id) {
        echo "[SKIP] Admin role not found.\n";
        return false;
    }

    $sql = "INSERT INTO employees (company_id, first_name, last_name, username, role_id, employment_status_id) 
            VALUES ($company_id, 'Test', 'Admin', '$username', $role_id, $status_id)";
    
    if (!mysqli_query($conn, $sql)) {
        echo "[EXPECTED FAIL] Employee insert failed (as predicted): " . mysqli_error($conn) . "\n";
    } else {
        echo "[SUCCESS] Employee insert successful. The trigger issue is NOT present.\n";
    }
    return true;
}

// Main execution
echo "Starting Database Integrity Reproduction...\n";
mysqli_begin_transaction($conn);
try {
    test_expenses($conn);
    test_assignment_history($conn);
    test_employee_trigger($conn);
} finally {
    mysqli_rollback($conn);
    echo "\nReproduction sequence completed. Changes rolled back.\n";
}
