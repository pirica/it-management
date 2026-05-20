<?php
/**
 * Ensure canonical equipment-type façade modules exist under modules/is_*.
 *
 * CLI: php scripts/ensure_equipment_type_modules.php
 *
 * Does not remove anything; only creates missing is_workstation, is_switch, … wrappers.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';
require __DIR__ . '/lib/equipment_type_modules.php';

$ensured = itm_ensure_canonical_equipment_type_modules($conn);
$canonical = itm_canonical_equipment_is_module_names();
$present = 0;
$modulesRoot = dirname(__DIR__) . '/modules';

foreach ($canonical as $moduleName) {
    $indexPath = $modulesRoot . '/' . $moduleName . '/index.php';
    if (is_file($indexPath)) {
        $present++;
        fwrite(STDOUT, "[OK] modules/{$moduleName}/index.php\n");
        continue;
    }
    fwrite(STDOUT, "[MISSING] modules/{$moduleName}/index.php\n");
}

fwrite(STDOUT, "\nCanonical modules present: {$present}/" . count($canonical) . "\n");
fwrite(STDOUT, "Scaffold calls succeeded: {$ensured}\n");

exit($present === count($canonical) ? 0 : 1);
