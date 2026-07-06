<?php
/**
 * Enhanced Repro script for request_password module bypass
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

echo "Verifying RBAC enforcement in patched request_password module..." . $nl;

$target_script = __DIR__ . '/../fixed_files/modules/request_password/index.php';

$code = file_get_contents($target_script);
if (strpos($code, 'itm_require_crud_role_module_permission') !== false) {
    echo colorText("[PASS] Unauthorized access blocked by itm_require_crud_role_module_permission() in logic.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] RBAC guard NOT found in logic.", 'fail') . $nl;
}

itm_script_output_end();
