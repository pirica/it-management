<?php
/**
 * Reproduction script for Employee Status Leak and Broken Access Control.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../scripts/lib/script_cli_output.php';
require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");

itm_script_output_begin('Employee Status Leak & BAC Reproduction');

function test_emp_status_id_from_raw_leak($conn, $nl) {
    echo "1. Testing emp_status_id_from_raw Cross-Tenant Leak..." . $nl;

    $company_a = 1;
    $company_b = 2;

    // Ensure company A has 'Inactive' status
    $resA = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE company_id = $company_a AND name = 'Inactive' LIMIT 1");
    $rowA = mysqli_fetch_assoc($resA);
    $statusIdA = $rowA['id'] ?? 0;

    if ($statusIdA <= 0) {
        echo "Creating 'Inactive' status for Company A..." . $nl;
        mysqli_query($conn, "INSERT INTO employee_statuses (company_id, name) VALUES ($company_a, 'Inactive')");
        $statusIdA = mysqli_insert_id($conn);
    }

    echo "Company A 'Inactive' status ID: $statusIdA" . $nl;

    // Ensure company B does NOT have 'Inactive' status
    mysqli_query($conn, "DELETE FROM employee_statuses WHERE company_id = $company_b AND name = 'Inactive'");

    // Original vulnerable implementation (copied for repro)
    $vulnerable_func = function($conn, $rawStatus) {
        $status = strtoupper(trim((string)$rawStatus));
        $name = 'Active';
        if ($status === 'I' || $status === 'INACTIVE') {
            $name = 'Inactive';
        }
        $nameEsc = mysqli_real_escape_string($conn, $name);
        // LEAK: Missing company_id filter
        $q = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE name='{$nameEsc}' LIMIT 1");
        if ($q && mysqli_num_rows($q) === 1) {
            $row = mysqli_fetch_assoc($q);
            return (int)$row['id'];
        }
        // CRASH: Missing company_id on INSERT
        if (mysqli_query($conn, "INSERT INTO employee_statuses (name) VALUES ('{$nameEsc}')")) {
            return (int)mysqli_insert_id($conn);
        }
        return 1;
    };

    $resolvedId = $vulnerable_func($conn, 'I');
    echo "Resolved status ID for Company B: $resolvedId" . $nl;

    if ($resolvedId == $statusIdA) {
        echo colorText("[FAIL] VULNERABILITY CONFIRMED: Leaked status ID from Company A to Company B.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] No leak detected (vulnerable function might have failed).", 'pass') . $nl;
    }
}

function test_employee_companies_import_label_leak($conn, $nl) {
    echo "2. Testing employee_companies user label Cross-Tenant Leak..." . $nl;

    $company_a = 1;
    $company_b = 2;

    // Find a user in Company A that is NOT in Company B
    $resA = mysqli_query($conn, "SELECT id, username FROM employees WHERE company_id = $company_a LIMIT 1");
    $rowA = mysqli_fetch_assoc($resA);
    $userIdA = $rowA['id'] ?? 0;
    $usernameA = $rowA['username'] ?? '';

    if ($userIdA <= 0) {
        echo "No user found in Company A." . $nl;
        return;
    }

    echo "User in Company A: $usernameA (ID: $userIdA)" . $nl;

    // Vulnerable cr_import_user_display_label
    $vulnerable_label_func = function($conn, int $employeeId) {
        if ($employeeId <= 0) return 'Unknown user';
        // LEAK: Missing company_id filter
        $sql = 'SELECT username, first_name, last_name FROM employees WHERE id=' . (int)$employeeId . ' LIMIT 1';
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!is_array($row)) return 'User #' . $employeeId;
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName !== '') return $fullName;
        $username = trim((string)($row['username'] ?? ''));
        return $username !== '' ? $username : ('User #' . $employeeId);
    };

    // Simulate Admin of Company B looking up user ID from Company A
    $label = $vulnerable_label_func($conn, $userIdA);
    echo "Resolved label for User ID $userIdA: $label" . $nl;

    if (strpos($label, $usernameA) !== false || $label !== "User #$userIdA") {
        echo colorText("[FAIL] VULNERABILITY CONFIRMED: Admin of Company B can see User identity from Company A.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] User identity from Company A is hidden.", 'pass') . $nl;
    }
}

test_emp_status_id_from_raw_leak($conn, $nl);
test_employee_companies_import_label_leak($conn, $nl);

itm_script_output_end();
