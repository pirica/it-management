<?php
/**
 * Cleanup regression-test pollution only (never canonical modules/is_* façades).
 *
 * Removes:
 *   - modules/is_*_*itm_eqdct* / *_itm_edct* test scaffold folders
 *   - modules/is_mbqa_equipment_types_{company}_{hash} orphan folders (QA runner on equipment_types)
 *   - equipment_types rows with itm_eqdct / itm_edct in the name, or MBQA-equipment_types-… runner tags
 *   - ITM test companies
 *   - matching user_sidebar_preferences rows (itm_eqdct / itm_edct / is_mbqa_equipment_types_*)
 *
 * Also invoked automatically at the end of module_browser_qa_runner.php.
 *
 * CLI: php scripts/cleanup_equipment_test_module_artifacts.php
 * Restore façades: php scripts/ensure_equipment_type_modules.php
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Removes regression-test <code>equipment_types</code> rows (including <code>MBQA-equipment_types-…</code> runner tags), ITM test companies, junk <code>modules/is_*_itm_eqdct_*</code> and orphan <code>modules/is_mbqa_equipment_types_*</code> folders, and matching sidebar prefs — then re-ensures canonical <code>is_*</code> modules. Never removes <code>is_switch</code>, <code>is_server</code>, etc.</p>';
    echo '<p>Also runs automatically when a <code>module_browser_qa_runner</code> session finishes.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/cleanup_equipment_test_module_artifacts.php</pre>';
    echo '<p>Restore façades only: <code>php scripts/ensure_equipment_type_modules.php</code></p>';
    echo '</body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require __DIR__ . '/lib/equipment_type_modules.php';

$modulesRoot = dirname(__DIR__) . '/modules';
$cleanup = itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesRoot);

if ($cleanup['dirs_removed'] > 0) {
    fwrite(STDOUT, colorText("[OK] Removed {$cleanup['dirs_removed']} regression-test / QA scaffold module folder(s)", 'pass') . "\n");
} else {
    fwrite(STDOUT, colorText('[OK] No regression-test module folders to remove', 'pass') . "\n");
}

if ($cleanup['companies_deleted'] > 0) {
    fwrite(STDOUT, colorText("[OK] Removed {$cleanup['companies_deleted']} ITM test compan" . ($cleanup['companies_deleted'] === 1 ? 'y' : 'ies'), 'pass') . "\n");
}

if ($cleanup['types_deleted'] > 0) {
    fwrite(STDOUT, colorText("[OK] Removed {$cleanup['types_deleted']} equipment_types test row(s)", 'pass') . "\n");
} elseif ($cleanup['ok']) {
    fwrite(STDOUT, colorText('[OK] No equipment_types test rows to remove', 'pass') . "\n");
}

if ($cleanup['sidebar_deleted'] > 0) {
    fwrite(STDOUT, colorText("[OK] Removed {$cleanup['sidebar_deleted']} user_sidebar_preferences test row(s)", 'pass') . "\n");
}

fwrite(STDOUT, colorText("[OK] Verified canonical modules/is_* façades ({$cleanup['canonical_ensured']} scaffold pass(es))", 'pass') . "\n");

foreach ($cleanup['errors'] as $errorLine) {
    fwrite(STDERR, colorText('[FAIL] ' . $errorLine, 'fail') . "\n");
}

fwrite(STDOUT, "\nSummary: {$cleanup['dirs_removed']} test/QA scaffold folder(s) removed; canonical is_* modules preserved.\n");

exit($cleanup['ok'] ? 0 : 1);
