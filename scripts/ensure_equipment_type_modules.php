<?php
/**
 * Ensure canonical equipment-type façade modules exist under modules/is_*.
 *
 * CLI: php scripts/ensure_equipment_type_modules.php
 *
 * Does not remove anything; only creates missing is_workstation, is_switch, … wrappers.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Verifies or recreates canonical equipment-type façade modules under <code>modules/is_*</code> (<code>is_switch</code>, <code>is_server</code>, …). Does not delete anything.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/ensure_equipment_type_modules.php</pre>';
    echo '</body></html>';
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
