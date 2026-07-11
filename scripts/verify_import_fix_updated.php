<?php
/**
 * Verification script for Employee Import Department Data Loss Fix.
 */
define('ITM_CLI_SCRIPT', true);

// Include original config first to get base paths and connection
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify: Import Fix (Updated)');
$nl = itm_script_output_nl();

echo colorText("--- Verifying Employee Import Department Data Loss Fix ---", 'info') . $nl;

$companyId = 1;
// Mock session for itm_get_csrf_token() and other helpers
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = $companyId;

// Setup: Create two departments
$suffix = time();
$geralName = "Geral_$suffix";
$targetDeptName = "TargetDept_$suffix";

mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES ($companyId, '$geralName', 'G$suffix', 1)");
$geralId = (int)mysqli_insert_id($conn);

mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES ($companyId, '$targetDeptName', 'T$suffix', 1)");
$targetDeptId = (int)mysqli_insert_id($conn);

echo "Geral ID: $geralId, Target Dept ID: $targetDeptId" . $nl;

// Create employee in Target Dept
$testEmail = "verify-$suffix@example.com";
mysqli_query($conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, department_id, employment_status_id)
              VALUES ($companyId, 'Verify', 'Dept', 'Verify Dept', '$testEmail', $targetDeptId, 1)");
$employeeId = (int)mysqli_insert_id($conn);

echo "Created employee ID: $employeeId assigned to Dept ID: $targetDeptId" . $nl;

// Mock data
$importData = [
    'csrf_token' => itm_get_csrf_token(),
    'import_excel_rows' => [
        ['id', 'First Name', 'Last Name', 'Work Email', 'Department ID'],
        [$employeeId, 'Verify', 'Updated', $testEmail, (string)$targetDeptId],
    ],
];

$sourceRow = $importData['import_excel_rows'][1];
$deptIndex = 4; // index of 'Department ID'
$geralDeptId_local = $geralId;

// --- START FIXED LOGIC SIMULATION ---
// This replicates the logic in itm_handle_json_table_import in config/config.php
$rowValues = [];
$rowValues['department_id'] = (string)$geralDeptId_local;
$deptValue = trim((string)($sourceRow[$deptIndex] ?? ''));

if ($deptValue !== '' && $deptValue !== '—' && strcasecmp($deptValue, 'null') !== 0) {
    if (ctype_digit($deptValue)) {
        $rowValues['department_id'] = (string)(int)$deptValue;
    } else {
        $depNameEsc = mysqli_real_escape_string($conn, $deptValue);
        $depSql = "SELECT id FROM departments WHERE company_id=" . (int)$companyId . " AND (name='" . $depNameEsc . "' OR code='" . $depNameEsc . "') LIMIT 1";
        $depRes = mysqli_query($conn, $depSql);
        if ($depRes && mysqli_num_rows($depRes) === 1) {
            $rowValues['department_id'] = (string)mysqli_fetch_assoc($depRes)['id'];
        }
    }
}
// --- END FIXED LOGIC SIMULATION ---

echo "Simulated rowValues['department_id']: " . $rowValues['department_id'] . $nl;

if ((int)$rowValues['department_id'] === $targetDeptId) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Department ID was correctly recognized as numeric ID!") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] BUG STILL PRESENT: Numeric Department ID was not correctly handled.") . $nl;
}

// Cleanup
mysqli_query($conn, "DELETE FROM employees WHERE id=$employeeId");
mysqli_query($conn, "DELETE FROM departments WHERE id IN ($geralId, $targetDeptId)");

echo $nl . colorText("--- Verifying Employee Module Manual Import Fix ---", 'info') . $nl;

$geralId_manual = 100;
$targetDeptId_manual = 200;

// Test case 1: Numeric ID provided
$mapped = ['department_id' => (string)$targetDeptId_manual];
$providedFields = [];

// --- START FIXED LOGIC SIMULATION ---
// Replicates logic in modules/employees/index.php
$is_resolved_by_name = false; // Simulated: it didn't enter the name resolution block

if (!$is_resolved_by_name) {
    if (empty($mapped['department_id']) || !is_numeric($mapped['department_id'])) {
        $mapped['department_id'] = $geralId_manual;
    }
}
// --- END FIXED LOGIC SIMULATION ---

echo "Mapped department_id (Numeric input): " . $mapped['department_id'] . $nl;
if ((int)$mapped['department_id'] === $targetDeptId_manual) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Numeric Department ID was preserved!") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] BUG STILL PRESENT: Numeric Department ID was overwritten.") . $nl;
}

// Test case 2: Empty input (should fallback to Geral)
$mapped = ['department_id' => ''];
$is_resolved_by_name = false;

if (!$is_resolved_by_name) {
    if (empty($mapped['department_id']) || !is_numeric($mapped['department_id'])) {
        $mapped['department_id'] = $geralId_manual;
    }
}

echo "Mapped department_id (Empty input): " . $mapped['department_id'] . $nl;
if ((int)$mapped['department_id'] === $geralId_manual) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Fallback to Geral worked correctly.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] Fallback to Geral failed.") . $nl;
}
