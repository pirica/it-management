<?php
/**
 * Verification script for Explorer Path Traversal fix
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');
define('ITM_VERIFY_SKIP_ROUTER', true);

// Mock server vars for config.php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/verify_explorer_fix.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Setup paths to allow inclusion from /docs/scripts/
$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/config.php';

// Mock session
$_SESSION['employee_id'] = 123;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'attacker';
$_SESSION['csrf_token'] = 'test_token';

// Why: itm-csrf-exempt: CLI-only verification script that mocks POST/session.
// itm_require_post_csrf();

require_once $projectRoot . '/includes/itm_explorer_paths.php';

// Include the FIXED file logic
require_once $projectRoot . '/docs/fixed_files_vulnerability_explorer/fixed_files/modules/explorer/api.php';

echo "Testing FIXED Explorer API\n";
echo "--------------------------\n";

// Case 1: Attempt traversal with item=..
$_POST['item'] = '..';

echo "Action: zip, Item: ..\n";
$safe_item = get_safe_post_item();
if ($safe_item === null) {
    echo "[PASS] Path Traversal '..' correctly blocked by get_safe_post_item().\n";
} else {
    echo "[FAIL] Path Traversal '..' allowed! Item: $safe_item\n";
}

// Case 2: Attempt traversal with item=sub/../../
$_POST['item'] = 'sub/../../';
echo "Action: zip, Item: sub/../../\n";
$safe_item = get_safe_post_item();
if ($safe_item === null) {
    echo "[PASS] Path Traversal with separators correctly blocked.\n";
} else {
    echo "[FAIL] Path Traversal with separators allowed! Item: $safe_item\n";
}

// Case 3: Valid item
$_POST['item'] = 'valid_file.txt';
echo "Action: zip, Item: valid_file.txt\n";
$safe_item = get_safe_post_item();
if ($safe_item === 'valid_file.txt') {
    echo "[PASS] Valid item correctly allowed.\n";
} else {
    echo "[FAIL] Valid item incorrectly blocked! Item: " . var_export($safe_item, true) . "\n";
}
