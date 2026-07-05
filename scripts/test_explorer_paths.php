<?php
/**
 * Explorer Path Validation Test (Pure Logic)
 */

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
    }
}

// Logic copied from api.php get_full_path (via includes/itm_explorer_paths.php)
require_once __DIR__ . '/../includes/itm_explorer_paths.php';

function get_full_path_logic($storage_root, $relative_path, $user_id, $dept_id, $username) {
    $relative_path = explorer_normalize_relative_path($relative_path);
    if ($relative_path === null) {
        return null;
    }

    $full = $storage_root . ($relative_path ? "/$relative_path" : "");

    if (strpos($full, $storage_root) !== 0) return null;

    $safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
    $user_private_dir = "{$safe_username}_{$user_id}";

    if ($relative_path === 'Private' || str_starts_with($relative_path, 'Private/')) {
        if ($relative_path === 'Private') return null;
        if (!str_starts_with($relative_path, "Private/$user_private_dir/") &&
            $relative_path !== "Private/$user_private_dir") {
            return null;
        }
    }

    if ($relative_path === 'Departments' || str_starts_with($relative_path, 'Departments/')) {
        if ($relative_path === 'Departments') return null;
        if ($dept_id <= 0) return null;
        if (!str_starts_with($relative_path, "Departments/$dept_id/") &&
            $relative_path !== "Departments/$dept_id") {
            return null;
        }
    }

    return $full;
}

$storage_root = '/app/files/1';
$user_id = 123;
$username = 'jdoe';
$user_private_dir = "jdoe_123";
$dept_id = 10;

$test_cases = [
    ['', 10, true, 'Home root'],
    ['/', 10, true, 'Home root (bypass /)'],
    ['Common', 10, true, 'Common folder'],
    ['Private', 10, false, 'Private root'],
    ['/Private/', 10, false, 'Private root (bypass /Private/)'],
    ['Private/' . $user_private_dir, 10, true, 'Own private folder'],
    ['Private/' . $user_private_dir . '/sub', 10, true, 'Subfolder in own private'],
    ['Private/other_456', 10, false, 'Other user private folder'],
    ['Private\\other_456\\secret', 10, false, 'Backslash other private path'],
    ['Departments', 10, false, 'Departments root'],
    ['Departments/' . $dept_id, 10, true, 'Own department folder'],
    ['Departments/20', 10, false, 'Other department folder'],
    ['Departments/10/sub', 10, true, 'Subfolder in own department'],
    ['..', 10, false, 'Traversal up'],
    ['Common/../Private', 10, false, 'Traversal attempt'],
    ['./Private', 10, false, 'Private root (bypass ./ prefix)'],
    ['./Private/other_456', 10, false, 'Other private folder (bypass ./ prefix)'],
    ['./Departments', 10, false, 'Departments root (bypass ./ prefix)'],
];

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Explorer Path Validation Test');
$nl = itm_script_output_nl();

$failed = 0;
foreach ($test_cases as $tc) {
    list($path, $d_id, $expected, $label) = $tc;
    $result = get_full_path_logic($storage_root, $path, $user_id, $d_id, $username);
    $success = ($result !== null);

    if ($success === $expected) {
        echo itm_script_format_status_line("[PASS] $label ($path): " . ($success ? "Allowed" : "Blocked")) . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] $label ($path): Expected " . ($expected ? "Allowed" : "Blocked") . " but got " . ($success ? "Allowed" : "Blocked")) . $nl;
        $failed++;
    }
}

if ($failed === 0) {
    echo $nl . colorText('All Explorer logic tests passed!', 'pass') . $nl;
} else {
    echo $nl . colorText("$failed Explorer logic tests failed!", 'fail') . $nl;
    exit(1);
}

itm_script_output_end();
