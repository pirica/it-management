<?php
/**
 * Auto-fix deployment script for user-config.php optimization.
 * Safely copies the optimized user-config.php to the application root directory.
 */

$root_dir = dirname(dirname(dirname(__DIR__)));
$source = dirname(__DIR__) . '/fixed_files_vulnerability_user_config/fixed_files/user-config.php';
$destination = $root_dir . '/user-config.php';

if (!file_exists($source)) {
    die("Error: Source optimized file not found at $source\n");
}

if (copy($source, $destination)) {
    echo "Success: Optimized user-config.php successfully deployed to $destination\n";
} else {
    echo "Error: Failed to copy optimized file to $destination\n";
    exit(1);
}
