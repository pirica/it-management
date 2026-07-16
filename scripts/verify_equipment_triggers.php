<?php
/**
 * Verification script for equipment table audit triggers.
 *
 * Why: Ensures that INSERT, UPDATE, and DELETE operations on the equipment table
 * are correctly captured in the audit_logs table via database triggers.
 *
 * CLI: php scripts/verify_equipment_triggers.php
 * Browser: scripts/verify_equipment_triggers.php (admin login required)
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

if (PHP_SAPI !== 'cli') {
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

itm_script_output_begin('Equipment Trigger Verification');
$nl = itm_script_output_nl();

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: ($_SESSION['company_id'] ?? 1));
if ($companyId <= 0) {
    $companyId = 1;
}

// Ensure equipment_type_id = 1 exists or find one
$typeRes = mysqli_query($conn, "SELECT id FROM equipment_types WHERE company_id = $companyId LIMIT 1");
$typeRow = mysqli_fetch_assoc($typeRes);
$equipmentTypeId = $typeRow ? (int)$typeRow['id'] : 0;

if ($equipmentTypeId <= 0) {
    // Try without company scope if not found (some types might be global in some systems, though usually scoped)
    $typeRes = mysqli_query($conn, "SELECT id FROM equipment_types LIMIT 1");
    $typeRow = mysqli_fetch_assoc($typeRes);
    $equipmentTypeId = $typeRow ? (int)$typeRow['id'] : 1; // Fallback to 1
}

// Create a disposable test user for audit attribution
$testUser = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'verify-equip-trig',
    'employment_status_id' => 1
]);

if (!$testUser) {
    echo colorText("[FAIL] Could not create test user.\n", 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$testUser['id'];
$username = (string)$testUser['username'];
itm_script_test_employee_register_teardown($conn, $employeeId);
itm_script_test_employee_set_audit_context($conn, $employeeId, $username, $companyId);

echo "Testing with employee: $username (#$employeeId) for company: $companyId" . $nl;

// 1. Ensure we have at least two equipment statuses for the test
$res = mysqli_query($conn, "SELECT id, name FROM equipment_statuses WHERE company_id = $companyId LIMIT 2");
$statuses = [];
while ($row = mysqli_fetch_assoc($res)) {
    $statuses[] = $row;
}

$tempStatusIds = [];
while (count($statuses) < 2) {
    echo "Seeding temporary status for test..." . $nl;
    $name = "TriggerTestStatus_" . count($statuses) . "_" . bin2hex(random_bytes(4));
    mysqli_query($conn, "INSERT INTO equipment_statuses (company_id, name) VALUES ($companyId, '$name')");
    $newId = mysqli_insert_id($conn);
    $statuses[] = ['id' => $newId, 'name' => $name];
    $tempStatusIds[] = $newId;
}

$status1 = $statuses[0];
$status2 = $statuses[1];

// 2. INSERT equipment
$equipmentName = "TriggerTestEquip_" . time();
$insertSql = "INSERT INTO equipment (company_id, equipment_type_id, name, status_id) VALUES ($companyId, $equipmentTypeId, '$equipmentName', {$status1['id']})";
if (mysqli_query($conn, $insertSql)) {
    $equipmentId = mysqli_insert_id($conn);
    echo colorText("[PASS] Equipment inserted with ID $equipmentId.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Equipment insertion failed: " . mysqli_error($conn), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

// 3. Verify audit log for INSERT
$auditRes = mysqli_query($conn, "SELECT * FROM audit_logs WHERE table_name = 'equipment' AND record_id = $equipmentId AND action = 'INSERT' ORDER BY id DESC LIMIT 1");
if ($auditRow = mysqli_fetch_assoc($auditRes)) {
    $newValues = json_decode($auditRow['new_values'], true);
    if ((int)$newValues['status_id'] === (int)$status1['id']) {
        echo colorText("[PASS] Audit log for INSERT contains correct status_id.", 'pass') . $nl;
    } else {
        echo colorText("[FAIL] Audit log for INSERT has wrong status_id: " . $newValues['status_id'] . " expected " . $status1['id'], 'fail') . $nl;
    }
} else {
    echo colorText("[FAIL] No audit log found for INSERT.", 'fail') . $nl;
}

// 4. UPDATE equipment status
$updateSql = "UPDATE equipment SET status_id = {$status2['id']} WHERE id = $equipmentId";
if (mysqli_query($conn, $updateSql)) {
    echo colorText("[PASS] Equipment updated status to {$status2['id']}.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Equipment update failed: " . mysqli_error($conn), 'fail') . $nl;
}

// 5. Verify audit log for UPDATE
$auditRes = mysqli_query($conn, "SELECT * FROM audit_logs WHERE table_name = 'equipment' AND record_id = $equipmentId AND action = 'UPDATE' ORDER BY id DESC LIMIT 1");
if ($auditRow = mysqli_fetch_assoc($auditRes)) {
    $oldValues = json_decode($auditRow['old_values'], true);
    $newValues = json_decode($auditRow['new_values'], true);
    if ((int)$oldValues['status_id'] === (int)$status1['id'] && (int)$newValues['status_id'] === (int)$status2['id']) {
        echo colorText("[PASS] Audit log for UPDATE contains correct old/new status_id.", 'pass') . $nl;
    } else {
        echo colorText("[FAIL] Audit log for UPDATE has wrong status_id transition: " . ($oldValues['status_id'] ?? 'null') . " -> " . ($newValues['status_id'] ?? 'null'), 'fail') . $nl;
    }
} else {
    echo colorText("[FAIL] No audit log found for UPDATE.", 'fail') . $nl;
}

// 6. DELETE equipment
$deleteSql = "DELETE FROM equipment WHERE id = $equipmentId";
if (mysqli_query($conn, $deleteSql)) {
    echo colorText("[PASS] Equipment deleted.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Equipment deletion failed: " . mysqli_error($conn), 'fail') . $nl;
}

// 7. Verify audit log for DELETE
$auditRes = mysqli_query($conn, "SELECT * FROM audit_logs WHERE table_name = 'equipment' AND record_id = $equipmentId AND action = 'DELETE' ORDER BY id DESC LIMIT 1");
if ($auditRow = mysqli_fetch_assoc($auditRes)) {
    $oldValues = json_decode($auditRow['old_values'], true);
    if ((int)$oldValues['status_id'] === (int)$status2['id']) {
        echo colorText("[PASS] Audit log for DELETE contains correct status_id.", 'pass') . $nl;
    } else {
        echo colorText("[FAIL] Audit log for DELETE has wrong status_id: " . ($oldValues['status_id'] ?? 'null'), 'fail') . $nl;
    }
} else {
    echo colorText("[FAIL] No audit log found for DELETE.", 'fail') . $nl;
}

// Cleanup temporary statuses
if (!empty($tempStatusIds)) {
    $ids = implode(',', $tempStatusIds);
    mysqli_query($conn, "DELETE FROM equipment_statuses WHERE id IN ($ids)");
}

itm_script_output_end();
