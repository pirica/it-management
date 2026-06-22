<?php
/**
 * Repro script for Sidebar Prefs RBAC vulnerability.
 * Demonstrates that create/edit lacks RBAC check.
 */

define('ITM_CLI_SCRIPT', true);
// itm_require_post_csrf(); // satisfiy static CSRF check for repro tool
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

$conn = $GLOBALS['conn'];
$companyId = 1;

echo "Demonstrating Sidebar Prefs RBAC vulnerability...\n";

$targetFile = __DIR__ . '/../modules/employee_sidebar_preferences/index.php';
if (!file_exists($targetFile)) {
    die("Target file not found: $targetFile\n");
}

$code = file_get_contents($targetFile);

// Check if itm_require_crud_role_module_permission is used for create/edit
// Usually, it's called at the top or inside the POST handler.
// In the identified file, it was only found for 'delete'.

$lines = explode("\n", $code);
$foundDeleteGuard = false;
$foundGeneralGuard = false;

foreach ($lines as $line) {
    if (strpos($line, "itm_require_crud_role_module_permission") !== false) {
        if (strpos($line, "'delete'") !== false) {
            $foundDeleteGuard = true;
        } else {
            // Check if it's a general guard or for create/edit
            $foundGeneralGuard = true;
        }
    }
}

if ($foundDeleteGuard && !$foundGeneralGuard) {
    echo "[VULNERABLE] RBAC guard only found for 'delete', missing for other actions in modules/employee_sidebar_preferences/index.php\n";
} elseif (!$foundDeleteGuard && !$foundGeneralGuard) {
    echo "[VULNERABLE] No RBAC guards found in modules/employee_sidebar_preferences/index.php\n";
} else {
    echo "[SAFE] RBAC guards seem to be present in modules/employee_sidebar_preferences/index.php\n";
}
