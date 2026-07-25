<?php
/**
 * Explorer Path Validation Test (Pure Logic)
 */

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
    }
}

require_once __DIR__ . '/../includes/itm_explorer_paths.php';

function get_full_path_logic($storage_root, $relative_path, $user_id, $dept_codes, $username) {
    if (!is_array($dept_codes)) {
        $dept_codes = $dept_codes === '' ? [] : [(string)$dept_codes];
    }
    $normalizedCodes = [];
    foreach ($dept_codes as $code) {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$code);
        if ($safe !== '') {
            $normalizedCodes[$safe] = $safe;
        }
    }
    $dept_codes = array_values($normalizedCodes);

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
        if ($relative_path === 'Departments') {
            return $full;
        }
        $parts = explode('/', $relative_path);
        $segment = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $parts[1] ?? '');
        if ($segment === '' || !in_array($segment, $dept_codes, true)) {
            return null;
        }
    }

    return $full;
}

$storage_root = '/app/files/1';
$user_id = 123;
$username = 'jdoe';
$user_private_dir = "jdoe_123";
$dept_codes = ['IT', 'FO'];

$test_cases = [
    ['', $dept_codes, true, 'Home root'],
    ['/', $dept_codes, true, 'Home root (bypass /)'],
    ['Common', $dept_codes, true, 'Common folder'],
    ['Private', $dept_codes, false, 'Private root'],
    ['/Private/', $dept_codes, false, 'Private root (bypass /Private/)'],
    ['Private/' . $user_private_dir, $dept_codes, true, 'Own private folder'],
    ['Private/' . $user_private_dir . '/sub', $dept_codes, true, 'Subfolder in own private'],
    ['Private/other_456', $dept_codes, false, 'Other user private folder'],
    ['Private\\other_456\\secret', $dept_codes, false, 'Backslash other private path'],
    ['Departments', $dept_codes, true, 'Departments root'],
    ['Departments', [], true, 'Departments root without assignments'],
    ['Departments/IT', $dept_codes, true, 'Own department folder'],
    ['Departments/FO', $dept_codes, true, 'Second assigned department folder'],
    ['Departments/OTHER', $dept_codes, false, 'Other department folder'],
    ['Departments/IT/sub', $dept_codes, true, 'Subfolder in own department'],
    ['..', [], false, 'Traversal up'],
    ['Common/../Private', [], false, 'Traversal attempt'],
    ['./Private', [], false, 'Private root (bypass ./ prefix)'],
    ['./Private/other_456', [], false, 'Other private folder (bypass ./ prefix)'],
    ['./Departments', $dept_codes, true, 'Departments root (bypass ./ prefix)'],
];

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Explorer Path Validation Test');
$nl = itm_script_output_nl();

$failed = 0;
foreach ($test_cases as $tc) {
    list($path, $codes, $expected, $label) = $tc;
    $result = get_full_path_logic($storage_root, $path, $user_id, $codes, $username);
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
