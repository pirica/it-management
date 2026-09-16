<?php
/**
 * Command palette sidebar slug searchability regression.
 *
 * CLI: php scripts/verify_command_palette_sidebar_slugs.php
 * Browser: scripts/verify_command_palette_sidebar_slugs.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_command_palette_sidebar_slugs.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_command_palette_search.php</code>, <code>includes/ui_config.php</code> sidebar visibility, or command-palette module navigation. Asserts every module slug visible in the live Admin sidebar (company 1) is findable via Ctrl+K module navigation.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/../includes/itm_command_palette_search.php';
require_once __DIR__ . '/lib/itm_command_palette_sidebar_verify.php';

itm_script_output_begin('Command Palette Sidebar Slugs Verification');

$nl = itm_script_output_nl();
$failures = 0;

function cps_sidebar_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function cps_sidebar_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    cps_sidebar_verify_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$adminStmt = mysqli_prepare(
    $conn,
    "SELECT id FROM employees WHERE company_id = ? AND username = 'Admin' AND deleted_at IS NULL LIMIT 1"
);
$adminId = 0;
if ($adminStmt) {
    mysqli_stmt_bind_param($adminStmt, 'i', $companyId);
    mysqli_stmt_execute($adminStmt);
    $adminRow = mysqli_fetch_assoc(mysqli_stmt_get_result($adminStmt));
    mysqli_stmt_close($adminStmt);
    $adminId = (int)($adminRow['id'] ?? 0);
}

if ($adminId <= 0) {
    cps_sidebar_verify_fail('Seed Admin employee for company 1 not found.');
    exit(1);
}

cps_sidebar_verify_pass('Resolved seed Admin employee id ' . $adminId . '.');

if (!function_exists('itm_command_palette_sidebar_visible_module_slugs')) {
    cps_sidebar_verify_fail('Missing itm_command_palette_sidebar_visible_module_slugs() helper.');
    exit(1);
}

$audit = itm_command_palette_sidebar_verify_collect_misses($conn, $companyId, $adminId);
$sidebarSlugs = $audit['sidebar_slugs'] ?? [];

if ($sidebarSlugs === []) {
    cps_sidebar_verify_fail('Admin sidebar returned zero visible module slugs for company 1.');
} else {
    cps_sidebar_verify_pass('Admin sidebar exposes ' . count($sidebarSlugs) . ' searchable module slug(s).');
}

$navMisses = $audit['nav_misses'] ?? [];
if ($navMisses !== []) {
    $preview = implode(', ', array_slice($navMisses, 0, 8));
    if (count($navMisses) > 8) {
        $preview .= ' …';
    }
    cps_sidebar_verify_fail(
        count($navMisses) . ' sidebar slug(s) not returned by module navigation search: ' . $preview
    );
} else {
    cps_sidebar_verify_pass('Every visible sidebar slug is findable via module navigation search.');
}

$paletteMisses = $audit['palette_misses'] ?? [];
if ($paletteMisses !== []) {
    $preview = implode(', ', array_slice($paletteMisses, 0, 8));
    if (count($paletteMisses) > 8) {
        $preview .= ' …';
    }
    cps_sidebar_verify_fail(
        count($paletteMisses) . ' sidebar slug(s) missing from unified palette Modules group: ' . $preview
    );
} else {
    cps_sidebar_verify_pass('Every visible sidebar slug appears in the unified palette Modules group.');
}

if ($failures > 0) {
    echo $nl . colorText('FAILED: ' . $failures . ' check(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All sidebar slug palette checks passed.', 'pass') . $nl;
exit(0);
