<?php
/**
 * Verification script for Explorer Path Traversal fix (Updated)
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');
define('ITM_VERIFY_SKIP_ROUTER', true);

// Mock server vars for config.php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/verify_explorer_fix_updated.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Setup paths
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/scripts/lib/script_cli_output.php';

itm_script_output_begin();
$nl = itm_script_output_nl();

// Mock session
$_SESSION['employee_id'] = 123;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'attacker';
$_SESSION['csrf_token'] = 'test_token';

require_once $projectRoot . '/includes/itm_explorer_paths.php';

// Include the LIVE file logic
require_once $projectRoot . '/modules/explorer/api.php';

echo colorText("Testing Explorer API Fix (Updated)", 'info') . $nl;
echo "--------------------------" . $nl;

// Case 1: Attempt traversal with item=..
$_POST['item'] = '..';

echo "Action: zip, Item: .." . $nl;
$safe_item = get_safe_post_item();
if ($safe_item === null) {
    echo colorText("[PASS] Path Traversal '..' correctly blocked by get_safe_post_item().", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Path Traversal '..' allowed! Item: $safe_item", 'fail') . $nl;
}

// Case 2: Attempt traversal with item=sub/../../
$_POST['item'] = 'sub/../../';
echo "Action: zip, Item: sub/../../" . $nl;
$safe_item = get_safe_post_item();
if ($safe_item === null) {
    echo colorText("[PASS] Path Traversal with separators correctly blocked.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Path Traversal with separators allowed! Item: $safe_item", 'fail') . $nl;
}

// Case 3: Valid item
$_POST['item'] = 'valid_file.txt';
echo "Action: zip, Item: valid_file.txt" . $nl;
$safe_item = get_safe_post_item();
if ($safe_item === 'valid_file.txt') {
    echo colorText("[PASS] Valid item correctly allowed.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Valid item incorrectly blocked! Item: " . var_export($safe_item, true), 'fail') . $nl;
}
?>
