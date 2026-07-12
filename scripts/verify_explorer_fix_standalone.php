<?php
/**
 * Standalone verification script for Explorer Path Traversal fix (for screenshot)
 */

/**
 * Why: Helper to safely extract and validate the 'item' parameter from POST.
 * Prevents path traversal by rejecting '..' and path separators.
 */
function get_safe_post_item_standalone($item_val) {
    $item = trim((string)($item_val ?? ''));
    if ($item === '..' || strpos($item, '/') !== false || strpos($item, '\\') !== false) {
        return null;
    }
    return basename($item);
}

require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer Fix Verification (Standalone)');
$nl = itm_script_output_nl();

echo colorText("Explorer Path Traversal Fix Verification (Standalone)", 'info') . $nl;

$testCases = [
    ['item' => '..', 'label' => "Action: zip, Item: .."],
    ['item' => 'sub/../../', 'label' => "Action: zip, Item: sub/../../"],
    ['item' => 'valid_file.txt', 'label' => "Action: zip, Item: valid_file.txt"]
];

foreach ($testCases as $case) {
    $safe_item = get_safe_post_item_standalone($case['item']);
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
