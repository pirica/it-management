<?php
/**
 * Enhanced Repro script for request_password module bypass
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/repro_request_password_bypass.php</code> · <code>php scripts/verify_request_password.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../scripts/lib/script_cli_output.php';

itm_script_output_begin('Verify: Request Password RBAC');
$nl = itm_script_output_nl();

// Mock session for a regular user
$_SESSION['employee_id'] = 999;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['role_name'] = 'User';

echo colorText("Verifying RBAC enforcement in request_password module...", 'info') . $nl;

$target_script = __DIR__ . '/../modules/request_password/index.php';

if (!is_file($target_script)) {
    die("Target script not found: modules/request_password/index.php" . $nl);
}

$code = file_get_contents($target_script);
if (strpos($code, 'itm_require_crud_role_module_permission') !== false || strpos($code, 'itm_require_admin') !== false) {
    echo itm_script_format_status_line("[PASS] RBAC guard found in logic.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] RBAC guard NOT found in logic.") . $nl;
}
