<?php
/**
 * Repro script for Explorer Path Traversal vulnerability
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');

// Mock server vars for config.php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/repro_explorer_traversal.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Setup paths
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/scripts/lib/script_cli_output.php';
require_once $projectRoot . '/includes/itm_explorer_paths.php';

itm_script_output_begin();
$nl = itm_script_output_nl();

/**
 * Replicate get_full_path from modules/explorer/api.php
 */
function repro_get_full_path($storage_root, $relative_path, $user_id, $dept_code, $username) {
    if (function_exists('explorer_normalize_relative_path')) {
        $relative_path = explorer_normalize_relative_path($relative_path);
    }
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
        if ($dept_code === '') return null;
        if (!str_starts_with($relative_path, "Departments/$dept_code/") &&
            $relative_path !== "Departments/$dept_code") {
            return null;
        }
    }

    return $full;
}

// Mock session for a regular user
$_SESSION['employee_id'] = 123;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'attacker';

$storage_root = ROOT_PATH . 'files/1';
$user_id = 123;
$dept_code = 'ATTACK'; // belongs to department ATTACK
$username = 'attacker';

// Case: User has access to Private/attacker_123
$path = 'Private/attacker_123';
$item = '..'; // Attacker attempts to go up to Private root

echo colorText("Simulating Action: zip", 'info') . $nl;
echo "Storage Root: $storage_root" . $nl;
echo "Path: $path" . $nl;
echo "Item: $item" . $nl . $nl;

// Simulating get_safe_post_item() from modules/explorer/api.php
function repro_get_safe_post_item($item) {
    $item = trim((string)($item ?? ''));
    if ($item === '..' || strpos($item, '/') !== false || strpos($item, '\\') !== false) {
        return null;
    }
    return basename($item);
}

$dir = repro_get_full_path($storage_root, $path, $user_id, $dept_code, $username);
$safe_item = repro_get_safe_post_item($item);

if ($dir && $safe_item !== null) {
    echo "Resolved Directory: $dir" . $nl;
    $src = $dir . "/" . $safe_item;
    echo "Source to Zip: $src" . $nl;

    $is_restricted = ($path === '' && in_array($safe_item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($path === 'Private' || $path === 'Departments');

    echo "is_restricted: " . ($is_restricted ? "TRUE" : "FALSE") . $nl;
    echo "is_sensitive_root: " . ($is_sensitive_root ? "TRUE" : "FALSE") . $nl;

    if (!$is_restricted && !$is_sensitive_root) {
        echo $nl . colorText("[VULNERABLE] Path Traversal via 'item=..' successful!", 'fail') . $nl;
        echo "The zip operation would process: " . $src . " (which is the root Private folder containing other users' data)." . $nl;
    } else {
        echo $nl . colorText("[SAFE] Path Traversal blocked.", 'pass') . $nl;
    }
} else {
    echo colorText("[SAFE] Path Access Denied.", 'pass') . $nl;
}
?>
