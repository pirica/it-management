<?php
/**
 * Web-friendly verification script for Explorer Path Traversal fix
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');
define('ITM_VERIFY_SKIP_ROUTER', true);

// Setup paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/itm_explorer_paths.php';

// Include the LIVE file logic
require_once ROOT_PATH . 'modules/explorer/api.php';

// Mock session
$_SESSION['employee_id'] = 123;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'attacker';
$_SESSION['csrf_token'] = 'test_token';

itm_script_output_begin('Explorer Fix Verification (Web)');
$nl = itm_script_output_nl();

echo colorText("Explorer Path Traversal Fix Verification (Web)", 'info') . $nl;

$testCases = [
    ['item' => '..', 'label' => "Action: zip, Item: .."],
    ['item' => 'sub/../../', 'label' => "Action: zip, Item: sub/../../"],
    ['item' => 'valid_file.txt', 'label' => "Action: zip, Item: valid_file.txt"]
];

foreach ($testCases as $case) {
    $_POST['item'] = $case['item'];
    $safe_item = get_safe_post_item();
    $isPass = false;
    $message = "";

    if ($case['item'] === 'valid_file.txt') {
        if ($safe_item === 'valid_file.txt') {
            $isPass = true;
            $message = "Valid item correctly allowed.";
        } else {
            $message = "Valid item incorrectly blocked!";
        }
    } else {
        if ($safe_item === null) {
            $isPass = true;
            $message = "Path Traversal correctly blocked.";
        } else {
            $message = "Path Traversal allowed! Item: " . (string)$safe_item;
        }
    }

    echo "CASE: " . $case['label'] . $nl;
    echo "Result: " . $message . $nl;
    echo itm_script_format_status_line($isPass ? "[PASS]" : "[FAIL]") . $nl . $nl;
}

itm_script_output_end();
// Standard: scripts/SCRIPTS.md
