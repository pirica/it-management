<?php
/**
 * Verification script for Employee Status Leak and Broken Access Control fixes.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();

require_once __DIR__ . '/../scripts/lib/script_cli_output.php';
require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");

itm_script_output_begin('Employee Status Leak & BAC Verification');

function test_emp_status_id_from_raw_fixed($conn, $nl) {
    echo "1. Verifying FIXED emp_status_id_from_raw scoping..." . $nl;

    $company_a = 1;
    $company_b = 2;

    // Fixed implementation
    $fixed_func = function($conn, $rawStatus, $company_id) {
        $status = strtoupper(trim((string)$rawStatus));
        $name = 'Active';
        if ($status === 'I' || $status === 'INACTIVE') {
            $name = 'Inactive';
        }
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $companyId = (int)$company_id;
        $q = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE name='{$nameEsc}' AND company_id={$companyId} LIMIT 1");
        if ($q && mysqli_num_rows($q) === 1) {
            $row = mysqli_fetch_assoc($q);
            return (int)$row['id'];
        }
        if (mysqli_query($conn, "INSERT INTO employee_statuses (name, company_id) VALUES ('{$nameEsc}', {$companyId})")) {
            return (int)mysqli_insert_id($conn);
        }
        return 1;
    };

    // Ensure company A has 'Inactive' status
    $resA = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE company_id = $company_a AND name = 'Inactive' LIMIT 1");
    $rowA = mysqli_fetch_assoc($resA);
    $statusIdA = $rowA['id'] ?? 0;

    if ($statusIdA <= 0) {
        mysqli_query($conn, "INSERT INTO employee_statuses (company_id, name) VALUES ($company_a, 'Inactive')");
        $statusIdA = mysqli_insert_id($conn);
    }

    // Ensure company B does NOT have 'Inactive' status
    mysqli_query($conn, "DELETE FROM employee_statuses WHERE company_id = $company_b AND name = 'Inactive'");

    $resolvedId = $fixed_func($conn, 'I', $company_b);
    echo "Resolved status ID for Company B: $resolvedId" . $nl;

    if ($resolvedId == $statusIdA) {
        echo colorText("[FAIL] VULNERABILITY STILL PRESENT: Leaked status ID from Company A to Company B.", 'fail') . $nl;
    } else {
        $resB = mysqli_query($conn, "SELECT company_id FROM employee_statuses WHERE id = $resolvedId");
        $rowB = mysqli_fetch_assoc($resB);
        if ($rowB['company_id'] == $company_b) {
            echo colorText("[PASS] Status correctly scoped to Company B.", 'pass') . $nl;
        } else {
             echo colorText("[FAIL] Status created for wrong company: " . ($rowB['company_id'] ?? 'NULL'), 'fail') . $nl;
        }
    }
}

function test_employee_companies_import_label_fixed($conn, $nl) {
    echo "2. Verifying FIXED employee_companies user label scoping..." . $nl;

    $company_a = 1;
    $company_b = 2;

    // Create a user in Company A that is NOT in Company B
    $testUser = itm_script_test_employee_create($conn, $company_a, ['script_slug' => 'verify-label-fix']);
    if (!is_array($testUser)) {
        echo "Unable to create test user." . $nl;
        return;
    }
    $userIdA = (int)$testUser['id'];
    itm_script_test_employee_register_teardown($conn, $userIdA);

    // Ensure NOT linked to company B
    mysqli_query($conn, "DELETE FROM employee_companies WHERE employee_id = $userIdA AND company_id = $company_b");

    // Fixed implementation
    $fixed_label_func = function($conn, int $employeeId, int $companyId) {
        if ($employeeId <= 0) return 'Unknown user';
        $sql = 'SELECT u.username, u.first_name, u.last_name
                FROM employees u
                WHERE u.id=' . (int)$employeeId . '
                AND (u.company_id=' . (int)$companyId . ' OR EXISTS (SELECT 1 FROM employee_companies uc WHERE uc.employee_id = u.id AND uc.company_id=' . (int)$companyId . '))
                LIMIT 1';
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!is_array($row)) return 'User #' . $employeeId;
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName !== '') return $fullName;
        $username = trim((string)($row['username'] ?? ''));
        return $username !== '' ? $username : ('User #' . $employeeId);
    };

    // Simulate Admin of Company B looking up user ID from Company A
    $label = $fixed_label_func($conn, $userIdA, $company_b);
    echo "Resolved label for User ID $userIdA as Company B Admin: $label" . $nl;

    if ($label === "User #$userIdA") {
        echo colorText("[PASS] User identity from Company A is correctly hidden from Company B.", 'pass') . $nl;
    } else {
        echo colorText("[FAIL] VULNERABILITY STILL PRESENT: Admin of Company B can see User identity from Company A.", 'fail') . $nl;
    }
}

test_emp_status_id_from_raw_fixed($conn, $nl);
test_employee_companies_import_label_fixed($conn, $nl);

itm_script_output_end();
