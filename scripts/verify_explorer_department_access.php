<?php
/**
 * Verify Explorer department folder listing and ACL (multi-department assignments).
 */
define('ITM_CLI_SCRIPT', true);
define('ITM_VERIFY_SKIP_ROUTER', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/explorer/explorer_storage_helpers.php';
require_once __DIR__ . '/../modules/explorer/api.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer Department Access Verification');
$nl = itm_script_output_nl();
$failed = 0;

$storageRoot = sys_get_temp_dir() . '/itm_explorer_dept_acl_' . getmypid();
@mkdir($storageRoot . '/Departments/FNB', 0777, true);
@mkdir($storageRoot . '/Departments/FO', 0777, true);
@mkdir($storageRoot . '/Departments/HR', 0777, true);

$allowedCodes = ['FNB', 'FO'];
$userId = 99;
$username = 'demo6';

$assert = static function ($label, $condition) use (&$failed, $nl) {
    if ($condition) {
        echo itm_script_format_status_line("[PASS] {$label}") . $nl;
        return;
    }
    echo itm_script_format_status_line("[FAIL] {$label}") . $nl;
    $failed++;
};

$assert('Departments root is listable', get_full_path($storageRoot, 'Departments', $userId, $allowedCodes, $username) !== null);
$assert('Assigned code folder is allowed', get_full_path($storageRoot, 'Departments/FNB', $userId, $allowedCodes, $username) !== null);
$assert('Second assigned code folder is allowed', get_full_path($storageRoot, 'Departments/FO', $userId, $allowedCodes, $username) !== null);
$assert('Unassigned code folder is blocked', get_full_path($storageRoot, 'Departments/HR', $userId, $allowedCodes, $username) === null);
$dirs = array_values(array_filter(scandir($storageRoot . '/Departments'), static function ($name) {
    return $name !== '.' && $name !== '..';
}));
$assert('Departments root shows every code folder on disk', count(array_intersect(['FNB', 'FO', 'HR'], $dirs)) === 3);

if (itm_employee_departments_table_exists($conn)) {
    $assert('employee_departments table exists', true);
} else {
    $assert('employee_departments table exists (apply db/migrations/employees_employee_departments.sql)', false);
}

if ($failed === 0) {
    echo $nl . itm_script_format_status_line('[PASS] Verification complete.') . $nl;
    exit(0);
}

echo $nl . itm_script_format_status_line("[FAIL] {$failed} check(s) failed.") . $nl;
exit(1);
