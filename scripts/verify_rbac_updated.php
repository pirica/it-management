<?php
/**
 * Validation Script: RBAC Protection
 *
 * Verifies that the fixed versions of module handlers contain the required
 * RBAC guards (itm_require_crud_role_module_permission).
 */

$testFiles = [
    __DIR__ . '/../fixed_files/modules/tickets/delete.php',
    __DIR__ . '/../fixed_files/modules/companies/delete.php',
    __DIR__ . '/../fixed_files/modules/private_contacts/create.php'
];

echo "RBAC Protection Validation\n";
echo "==========================\n";

$allPassed = true;
foreach ($testFiles as $file) {
    if (!file_exists($file)) {
        echo "[FAIL] File not found: " . basename(dirname($file)) . "/" . basename($file) . "\n";
        $allPassed = false;
        continue;
    }

    $content = file_get_contents($file);
    if (strpos($content, 'itm_require_crud_role_module_permission') !== false) {
        echo "[PASS] Guard found in: " . basename(dirname($file)) . "/" . basename($file) . "\n";
    } else {
        echo "[FAIL] Guard MISSING in: " . basename(dirname($file)) . "/" . basename($file) . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\nSUMMARY: All checked files are protected by RBAC guards.\n";
} else {
    echo "\nSUMMARY: Some files are missing protection.\n";
    exit(1);
}
