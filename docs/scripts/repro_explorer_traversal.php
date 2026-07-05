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

// Setup paths to allow inclusion from /docs/scripts/
$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/includes/itm_explorer_paths.php';

/**
 * Replicate get_full_path from modules/explorer/api.php
 */
function repro_get_full_path($storage_root, $relative_path, $user_id, $dept_id, $username) {
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
        if ($dept_id <= 0) return null;
        if (!str_starts_with($relative_path, "Departments/$dept_id/") &&
            $relative_path !== "Departments/$dept_id") {
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
$dept_id = 1; // belongs to department 1
$username = 'attacker';

// Case: User has access to Private/attacker_123
$path = 'Private/attacker_123';
$item = '..'; // Attacker attempts to go up to Private root

echo "Simulating Action: zip\n";
echo "Storage Root: $storage_root\n";
echo "Path: $path\n";
echo "Item: $item\n\n";

$dir = repro_get_full_path($storage_root, $path, $user_id, $dept_id, $username);

if ($dir) {
    echo "Resolved Directory: $dir\n";
    $src = $dir . "/" . basename($item);
    echo "Source to Zip: $src\n";

    // In modules/explorer/api.php, the zip action does:
    // $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    // $is_sensitive_root = ($path === 'Private' || $path === 'Departments');
    // if ($is_restricted || $is_sensitive_root) { ... }

    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($path === 'Private' || $path === 'Departments');

    echo "is_restricted: " . ($is_restricted ? "TRUE" : "FALSE") . "\n";
    echo "is_sensitive_root: " . ($is_sensitive_root ? "TRUE" : "FALSE") . "\n";

    if (!$is_restricted && !$is_sensitive_root) {
        echo "\n[VULNERABLE] Path Traversal via 'item=..' successful!\n";
        echo "The zip operation would process: " . $src . " (which is the root Private folder containing other users' data).\n";
    } else {
        echo "\n[SAFE] Path Traversal blocked.\n";
    }
} else {
    echo "[SAFE] Path Access Denied.\n";
}
