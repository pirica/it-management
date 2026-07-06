<?php
/**
 * Bolt Performance Auto-Fix - Equipment Module
 *
 * This script applies performance optimizations to:
 * 1. includes/itm_equipment_search.php
 * 2. modules/equipment/index.php
 */

require_once __DIR__ . '/../../../config/config.php';

$baseDir = __DIR__ . '/../fixed_files_performance_equipment/fixed_files';

$filesToFix = [
    'includes/itm_equipment_search.php' => ROOT_PATH . 'includes/itm_equipment_search.php',
    'modules/equipment/index.php' => ROOT_PATH . 'modules/equipment/index.php'
];

$successCount = 0;

foreach ($filesToFix as $sourceRel => $targetPath) {
    $sourcePath = $baseDir . '/' . $sourceRel;

    if (!is_file($sourcePath)) {
        echo "[ERROR] Source file not found: $sourcePath\n";
        continue;
    }

    $content = file_get_contents($sourcePath);
    if ($content === false) {
        echo "[ERROR] Unable to read source file: $sourcePath\n";
        continue;
    }

    // Backup existing file
    if (is_file($targetPath)) {
        copy($targetPath, $targetPath . '.bak.' . date('YmdHis'));
    }

    if (file_put_contents($targetPath, $content) !== false) {
        echo "[SUCCESS] Updated $targetPath\n";
        $successCount++;
    } else {
        echo "[ERROR] Failed to write to $targetPath\n";
    }
}

echo "\nTotal files updated: $successCount / " . count($filesToFix) . "\n";
