<?php
/**
 * Auto-fix for Dashboard performance.
 * (Theoretically applies optimization outside /docs/)
 */
$rootPath = dirname(dirname(dirname(__DIR__)));
$source = __DIR__ . '/../fixed_files_performance_dashboard/fixed_files/dashboard.php';
$target = $rootPath . '/dashboard.php';

if (is_file($source)) {
    if (copy($source, $target)) {
        echo "Successfully applied dashboard optimization.\n";
    } else {
        echo "Failed to apply dashboard optimization.\n";
    }
} else {
    echo "Source file not found.\n";
}
