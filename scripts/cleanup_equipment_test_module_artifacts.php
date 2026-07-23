<?php
/**
 * Cleanup regression-test pollution only (never canonical modules/is_* façades).
 *
 * Browser: dry-run by default; ?apply=1 (Admin) runs cleanup.
 * CLI: php scripts/cleanup_equipment_test_module_artifacts.php [--apply]
 *
 * Also invoked automatically at the end of module_browser_qa_runner.php.
 */

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/equipment_type_modules.php';

$boot = itm_apply_script_bootstrap('Equipment test module artifacts cleanup', ['skip_db_tests' => false]);
$nl = $boot['nl'];

$modulesRoot = dirname(__DIR__) . '/modules';

if (!$boot['apply']) {
    $preview = itm_preview_equipment_test_module_artifacts_cleanup($conn, $modulesRoot);
    itm_apply_script_echo_list('Would remove module folder(s)', $preview['dirs']);
    echo 'Would delete test companies: ' . (int)$preview['companies'] . $nl;
    echo 'Would delete equipment_types test rows: ' . (int)$preview['types'] . $nl;
    echo 'Would delete employee_sidebar_preferences test rows: ' . (int)$preview['sidebar'] . $nl;
    $wouldChange = ($preview['dirs'] !== [] || $preview['companies'] > 0 || $preview['types'] > 0 || $preview['sidebar'] > 0);
    itm_apply_script_finish_hint(false, $boot['is_cli'], $wouldChange ? 1 : 0, $nl, 'cleanup_equipment_test_module_artifacts.php');
    itm_script_output_end();
    exit(0);
}

$cleanup = itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesRoot);

if ($cleanup['dirs_removed'] > 0) {
    echo colorText("[OK] Removed {$cleanup['dirs_removed']} regression-test / QA scaffold module folder(s)", 'pass') . $nl;
} else {
    echo colorText('[OK] No regression-test module folders to remove', 'pass') . $nl;
}

if ($cleanup['companies_deleted'] > 0) {
    echo colorText("[OK] Removed {$cleanup['companies_deleted']} ITM test compan" . ($cleanup['companies_deleted'] === 1 ? 'y' : 'ies'), 'pass') . $nl;
}

if ($cleanup['types_deleted'] > 0) {
    echo colorText("[OK] Removed {$cleanup['types_deleted']} equipment_types test row(s)", 'pass') . $nl;
} elseif ($cleanup['ok']) {
    echo colorText('[OK] No equipment_types test rows to remove', 'pass') . $nl;
}

if ($cleanup['sidebar_deleted'] > 0) {
    echo colorText("[OK] Removed {$cleanup['sidebar_deleted']} employee_sidebar_preferences test row(s)", 'pass') . $nl;
}

echo colorText("[OK] Verified canonical modules/is_* façades ({$cleanup['canonical_ensured']} scaffold pass(es))", 'pass') . $nl;

foreach ($cleanup['errors'] as $errorLine) {
    echo colorText('[FAIL] ' . $errorLine, 'fail') . $nl;
}

echo $nl . "Summary: {$cleanup['dirs_removed']} test/QA scaffold folder(s) removed; canonical is_* modules preserved." . $nl;

itm_script_output_end();
exit($cleanup['ok'] ? 0 : 1);
