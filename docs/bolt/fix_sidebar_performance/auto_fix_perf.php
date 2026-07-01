<?php
/**
 * Auto-fix script for Sidebar Performance.
 *
 * This script applies the optimizations to:
 * - includes/ui_config.php (Static caching)
 * - includes/itm_company_module_access.php (Pre-fetching icons)
 */

$root = dirname(dirname(dirname(__DIR__)));
$filesToFix = [
    'includes/ui_config.php' => __DIR__ . '/../fixed_files_sidebar_performance/fixed_files/includes/ui_config.php',
    'includes/itm_company_module_access.php' => __DIR__ . '/../fixed_files_sidebar_performance/fixed_files/includes/itm_company_module_access.php'
];

foreach ($filesToFix as $relativePath => $fixedPath) {
    $targetPath = $root . '/' . $relativePath;
    if (file_exists($fixedPath)) {
        if (copy($fixedPath, $targetPath)) {
            echo "Successfully optimized: $relativePath\n";
        } else {
            echo "Failed to optimize: $relativePath\n";
        }
    } else {
        echo "Source file not found: $fixedPath\n";
    }
}
