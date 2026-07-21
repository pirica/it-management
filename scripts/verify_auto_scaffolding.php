<?php
/**
 * Verification script for Dynamic Auto-Scaffolding toggle.
 */

define('ITM_CLI_SCRIPT', true);

// Pre-set session variables to bypass slow backfills
@session_start();
$_SESSION['itm_ipam_equipment_link_backfill_done'] = 1;

// Mock server vars for config.php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/verify_auto_scaffolding.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Setup paths
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/scripts/lib/script_cli_output.php';

itm_script_output_begin('Verify: Dynamic Auto-Scaffolding');
$nl = itm_script_output_nl();

// Setup mock session for company 1 and employee 1
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'Admin';

$dummyTable = 'auto_scaffold_test_dummy';
$dummyModulePath = $projectRoot . '/modules/' . $dummyTable;

// Clean up any stale state first
if (is_dir($dummyModulePath)) {
    foreach (glob($dummyModulePath . '/*') as $f) {
        @unlink($f);
    }
    @rmdir($dummyModulePath);
}
mysqli_query($conn, "DROP TABLE IF EXISTS `$dummyTable`");

// 1. Create a dummy test table
echo "Creating dummy table `$dummyTable`..." . $nl;
$createSql = "CREATE TABLE `$dummyTable` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB";
if (!mysqli_query($conn, $createSql)) {
    echo colorText("[FAIL]", 'fail') . " Failed to create dummy table: " . mysqli_error($conn) . $nl;
    exit(1);
}

// 2. Test state 1: Auto-scaffolding DISABLED (default)
echo "Setting `enable_auto_scaffolding` to 0..." . $nl;
$updateConfigSql = "INSERT INTO `ui_configuration` (company_id, employee_id, enable_auto_scaffolding) VALUES (1, 1, 0) ON DUPLICATE KEY UPDATE enable_auto_scaffolding = 0";
mysqli_query($conn, $updateConfigSql);

// Clear UI configuration cache
itm_get_ui_configuration(null, 0, 0, true);

echo "Calling itm_sidebar_structure with auto-scaffolding disabled..." . $nl;
itm_sidebar_structure($conn, true);

if (is_dir($dummyModulePath)) {
    echo colorText("[FAIL]", 'fail') . " Module was scaffolded even though auto-scaffolding is disabled!" . $nl;
    // Clean up
    foreach (glob($dummyModulePath . '/*') as $f) {
        @unlink($f);
    }
    @rmdir($dummyModulePath);
    mysqli_query($conn, "DROP TABLE IF EXISTS `$dummyTable`");
    exit(1);
} else {
    echo colorText("[PASS]", 'pass') . " Successfully verified: no module was scaffolded when disabled." . $nl;
}

// 3. Test state 2: Auto-scaffolding ENABLED
echo "Setting `enable_auto_scaffolding` to 1..." . $nl;
$updateConfigSql = "INSERT INTO `ui_configuration` (company_id, employee_id, enable_auto_scaffolding) VALUES (1, 1, 1) ON DUPLICATE KEY UPDATE enable_auto_scaffolding = 1";
mysqli_query($conn, $updateConfigSql);

// Clear UI configuration cache
itm_get_ui_configuration(null, 0, 0, true);

echo "Calling itm_sidebar_structure with auto-scaffolding enabled..." . $nl;
itm_sidebar_structure($conn, true);

if (is_dir($dummyModulePath) && is_file($dummyModulePath . '/index.php')) {
    echo colorText("[PASS]", 'pass') . " Successfully verified: module was dynamically scaffolded when enabled." . $nl;
} else {
    echo colorText("[FAIL]", 'fail') . " Module was NOT scaffolded even though auto-scaffolding is enabled!" . $nl;
    // Clean up
    mysqli_query($conn, "DROP TABLE IF EXISTS `$dummyTable`");
    exit(1);
}

// Clean up
echo "Cleaning up dummy table and scaffolded files..." . $nl;
foreach (glob($dummyModulePath . '/*') as $f) {
    @unlink($f);
}
@rmdir($dummyModulePath);
mysqli_query($conn, "DROP TABLE IF EXISTS `$dummyTable`");

// Reset config back to default 0
mysqli_query($conn, "UPDATE `ui_configuration` SET `enable_auto_scaffolding` = 0 WHERE company_id = 1 AND employee_id = 1");

echo "All tests passed successfully!" . $nl;
itm_script_output_end();
