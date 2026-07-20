<?php
/**
 * Ensure canonical equipment-type façade modules exist under modules/is_*.
 *
 * Browser: dry-run lists missing wrappers; ?apply=1 (Admin) creates missing modules.
 * CLI: php scripts/ensure_equipment_type_modules.php [--apply]
 *
 * Does not remove anything; only creates missing is_workstation, is_switch, … wrappers.
 */

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/equipment_type_modules.php';

$boot = itm_apply_script_bootstrap('Ensure Equipment Type Modules', ['skip_db_tests' => false]);
$nl = $boot['nl'];

$modulesRoot = dirname(__DIR__) . '/modules';
$canonical = itm_canonical_equipment_is_module_names();
$present = 0;
$missing = [];

foreach ($canonical as $moduleName) {
    $indexPath = $modulesRoot . '/' . $moduleName . '/index.php';
    if (is_file($indexPath)) {
        $present++;
        echo "[OK] modules/{$moduleName}/index.php" . $nl;
        continue;
    }
    $missing[] = $moduleName;
    echo "[MISSING] modules/{$moduleName}/index.php" . $nl;
}

$ensured = 0;
if ($boot['apply'] && $missing !== []) {
    $ensured = itm_ensure_canonical_equipment_type_modules($conn);
    echo "Scaffold calls succeeded: {$ensured}" . $nl;
} elseif (!$boot['apply'] && $missing !== []) {
    echo 'Dry-run: re-run with --apply or ?apply=1 (Admin) to create missing façades.' . $nl;
}

echo $nl . "Canonical modules present: {$present}/" . count($canonical) . $nl;

$exitCode = ($present === count($canonical)) ? 0 : 1;
if ($boot['apply'] && $ensured > 0 && $exitCode === 1) {
    // Re-check after scaffold
    $presentAfter = 0;
    foreach ($canonical as $moduleName) {
        if (is_file($modulesRoot . '/' . $moduleName . '/index.php')) {
            $presentAfter++;
        }
    }
    $exitCode = ($presentAfter === count($canonical)) ? 0 : 1;
}

itm_apply_script_finish_hint($boot['apply'], $boot['is_cli'], count($missing), $nl, 'ensure_equipment_type_modules.php');
itm_script_output_end();
exit($exitCode);
