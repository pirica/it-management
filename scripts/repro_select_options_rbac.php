<?php
/**
 * Enhanced Repro script for Select Options RBAC bypass
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify: Select Options RBAC');
$nl = itm_script_output_nl();

// Mock session for a regular user
$_SESSION['employee_id'] = 999;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['role_name'] = 'User';
$_SESSION['csrf_token'] = 'test_token';

echo colorText("Verifying RBAC enforcement in Select Options API...", 'info') . $nl;

$target_script = __DIR__ . '/../modules/select_options_api.php';

if (!is_file($target_script)) {
    die("Target script not found: modules/select_options_api.php" . $nl);
}

$code = file_get_contents($target_script);
if (strpos($code, 'itm_require_crud_role_module_permission') !== false || strpos($code, 'itm_require_admin') !== false) {
    echo itm_script_format_status_line("[PASS] RBAC guard found in logic.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] RBAC guard NOT found in logic.") . $nl;
}
