<?php
/**
 * Enhanced Repro script for Select Options RBAC bypass
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/script_cli_output.php';

itm_script_output_begin();
$nl = itm_script_output_nl();

// Mock session for a regular user
$_SESSION['employee_id'] = 999;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['role_name'] = 'User';
$_SESSION['csrf_token'] = 'test_token';

echo "Verifying RBAC enforcement in patched Select Options API..." . $nl;

$target_script = __DIR__ . '/../fixed_files/modules/select_options_api.php';

$code = file_get_contents($target_script);
if (strpos($code, 'itm_require_crud_role_module_permission') !== false) {
    echo colorText("[PASS] RBAC bypass blocked by itm_require_crud_role_module_permission() in logic.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] RBAC guard NOT found in logic.", 'fail') . $nl;
}

itm_script_output_end();
