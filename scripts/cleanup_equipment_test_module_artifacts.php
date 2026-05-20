<?php
/**
 * Cleanup regression-test pollution only (never canonical modules/is_* façades).
 *
 * Removes:
 *   - modules/is_*_*itm_eqdct* / *_itm_edct* test scaffold folders only
 *   - equipment_types rows with itm_eqdct / itm_edct in the name
 *   - ITM test companies
 *   - matching user_sidebar_preferences rows
 *
 * Preserves: is_switch, is_server, is_workstation, is_printer, is_pos, …
 *
 * CLI: php scripts/cleanup_equipment_test_module_artifacts.php
 * Restore façades: php scripts/ensure_equipment_type_modules.php
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Removes regression-test <code>equipment_types</code> rows, ITM test companies, junk <code>modules/is_*_itm_eqdct_*</code> folders, and matching sidebar prefs — then re-ensures canonical <code>is_*</code> modules. Never removes <code>is_switch</code>, <code>is_server</code>, etc.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/cleanup_equipment_test_module_artifacts.php</pre>';
    echo '<p>Restore façades only: <code>php scripts/ensure_equipment_type_modules.php</code></p>';
    echo '</body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';
require __DIR__ . '/lib/equipment_type_modules.php';

mysqli_query($conn, 'SET @app_user_id = 1');
mysqli_query($conn, 'SET @app_company_id = 1');
mysqli_query($conn, "SET @app_username = 'cli-cleanup'");
mysqli_query($conn, "SET @app_email = 'cli-cleanup@example.com'");
mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
mysqli_query($conn, "SET @app_user_agent = 'cleanup_equipment_test_module_artifacts'");

$modulesRoot = dirname(__DIR__) . '/modules';
$dirRemoved = itm_remove_equipment_regression_test_module_dirs($modulesRoot);
if ($dirRemoved > 0) {
    fwrite(STDOUT, "[OK] Removed {$dirRemoved} regression-test module folder(s)\n");
} else {
    fwrite(STDOUT, "[OK] No regression-test module folders to remove\n");
}

$companiesDeleted = 0;
$companiesRes = mysqli_query(
    $conn,
    "DELETE FROM companies WHERE company LIKE 'ITM ClearTable Test %'
        OR company LIKE 'ITM Equipment ClearTable %'
        OR company LIKE 'ITM Debug %'"
);
if ($companiesRes) {
    $companiesDeleted = (int)mysqli_affected_rows($conn);
    fwrite(STDOUT, "[OK] Removed {$companiesDeleted} ITM test compan" . ($companiesDeleted === 1 ? 'y' : 'ies') . "\n");
} else {
    fwrite(STDERR, '[FAIL] companies cleanup: ' . mysqli_error($conn) . "\n");
}

$typesDeleted = 0;
$typesRes = mysqli_query(
    $conn,
    "DELETE FROM equipment_types WHERE name LIKE '%itm_eqdct%' OR name LIKE '%itm_edct%'"
);
if ($typesRes) {
    $typesDeleted = (int)mysqli_affected_rows($conn);
    fwrite(STDOUT, "[OK] Removed {$typesDeleted} equipment_types test row(s)\n");
} else {
    fwrite(STDERR, '[FAIL] equipment_types cleanup: ' . mysqli_error($conn) . "\n");
}

$sidebarDeleted = 0;
$sidebarRes = mysqli_query(
    $conn,
    "DELETE FROM user_sidebar_preferences WHERE entry_id LIKE '%itm_eqdct%' OR entry_id LIKE '%itm_edct%'"
);
if ($sidebarRes) {
    $sidebarDeleted = (int)mysqli_affected_rows($conn);
    fwrite(STDOUT, "[OK] Removed {$sidebarDeleted} user_sidebar_preferences test row(s)\n");
} else {
    fwrite(STDERR, '[FAIL] user_sidebar_preferences cleanup: ' . mysqli_error($conn) . "\n");
}

$ensured = itm_ensure_canonical_equipment_type_modules($conn);
fwrite(STDOUT, "[OK] Verified canonical modules/is_* façades ({$ensured} scaffold pass(es))\n");

fwrite(STDOUT, "\nSummary: {$dirRemoved} test folder(s) removed; canonical is_* modules preserved.\n");

$fail = !$sidebarRes || !$typesRes || !$companiesRes;
exit($fail ? 1 : 0);
