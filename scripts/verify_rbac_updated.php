<?php
/**
 * Validation Script: RBAC Protection
 *
 * Verifies that the fixed versions of module handlers contain the required
 * RBAC guards (itm_require_crud_role_module_permission).
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('RBAC Protection Validation');
$nl = itm_script_output_nl();

$testFiles = [
    __DIR__ . '/../modules/tickets/delete.php',
    __DIR__ . '/../modules/companies/delete.php',
    __DIR__ . '/../modules/private_contacts/create.php'
];

echo "RBAC Protection Validation" . $nl;
echo "==========================" . $nl;

$allPassed = true;
foreach ($testFiles as $f) {
    if (!file_exists($f)) {
        echo itm_script_format_status_line("[FAIL] File not found: " . basename(dirname($f)) . "/" . basename($f)) . $nl;
        $allPassed = false;
        continue;
    }

    $content = file_get_contents($f);
    if (strpos($content, 'itm_require_crud_role_module_permission') !== false) {
        echo itm_script_format_status_line("[PASS] Guard found in: " . basename(dirname($f)) . "/" . basename($f)) . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] Guard MISSING in: " . basename(dirname($f)) . "/" . basename($f)) . $nl;
        $allPassed = false;
    }
}

if ($allPassed) {
    echo $nl . itm_script_format_status_line("SUMMARY: All checked files are protected by RBAC guards.") . $nl;
} else {
    echo $nl . itm_script_format_status_line("SUMMARY: Some files are missing protection.") . $nl;
    exit(1);
}

itm_script_output_end();
