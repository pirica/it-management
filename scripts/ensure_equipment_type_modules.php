<?php
/**
 * Ensure canonical equipment-type façade modules exist under modules/is_*.
 *
 * CLI: php scripts/ensure_equipment_type_modules.php
 *
 * Does not remove anything; only creates missing is_workstation, is_switch, … wrappers.
 */

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    itm_script_output_begin('Ensure Equipment Type Modules');
    echo '<p><strong>CLI only.</strong> Verifies or recreates canonical equipment-type façade modules under <code>modules/is_*</code> (<code>is_switch</code>, <code>is_server</code>, …). Does not delete anything.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/ensure_equipment_type_modules.php</pre>';
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
        fwrite(STDOUT, "[OK] modules/{$moduleName}/index.php" . PHP_EOL);
        continue;
    }
    fwrite(STDOUT, "[MISSING] modules/{$moduleName}/index.php" . PHP_EOL);
}

fwrite(STDOUT, PHP_EOL . "Canonical modules present: {$present}/" . count($canonical) . PHP_EOL);
fwrite(STDOUT, "Scaffold calls succeeded: {$ensured}" . PHP_EOL);

exit($present === count($canonical) ? 0 : 1);
