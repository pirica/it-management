<?php
/**
 * Patches & Updates dashboard + calendar integration regression checks.
 *
 * CLI: php scripts/verify_patches_updates_integrations.php
 * Browser: scripts/verify_patches_updates_integrations.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_patches_updates_integrations.php?run=1">verify_patches_updates_integrations.php?run=1</a>. CLI: <code>php scripts/verify_patches_updates_integrations.php</code>. Run when changing patch calendar feed, dashboard widget, or list integration panel.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_patches_updates_integrations.php';
require_once ROOT_PATH . 'includes/itm_dashboard_widgets.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Patches Updates Integrations Verification');

$nl = itm_script_output_nl();
$failures = 0;

function pu_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function pu_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    pu_verify_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$helperPath = ROOT_PATH . 'includes/itm_patches_updates_integrations.php';
$indexPath = ROOT_PATH . 'modules/patches_updates/index.php';
$calendarPath = ROOT_PATH . 'modules/calendar/index.php';

foreach ([
    'includes/itm_patches_updates_integrations.php' => $helperPath,
    'modules/patches_updates/index.php' => $indexPath,
    'modules/calendar/index.php' => $calendarPath,
] as $label => $path) {
    if (!is_file($path)) {
        pu_verify_fail('Missing ' . $label);
    } else {
        pu_verify_pass($label . ' exists');
    }
}

$requiredFunctions = [
    'itm_patches_updates_due_within_days_count',
    'itm_patches_updates_list_calendar_rows',
    'itm_patches_updates_integration_summary',
    'itm_patches_updates_render_product_gaps_panel',
];
foreach ($requiredFunctions as $fn) {
    if (!function_exists($fn)) {
        pu_verify_fail('Missing helper ' . $fn . '()');
    } else {
        pu_verify_pass('Helper ' . $fn . '() loaded');
    }
}

$registry = itm_dashboard_widget_registry();
if (!isset($registry['patches_due_30d'])) {
    pu_verify_fail('Dashboard registry missing patches_due_30d widget');
} else {
    pu_verify_pass('Dashboard registry contains patches_due_30d');
}

$indexSource = is_file($indexPath) ? (string)file_get_contents($indexPath) : '';
$helperSource = is_file($helperPath) ? (string)file_get_contents($helperPath) : '';
if (strpos($indexSource, 'itm_patches_updates_render_product_gaps_panel') === false) {
    pu_verify_fail('patches_updates index must render product gaps panel');
} else {
    pu_verify_pass('List index renders Product gaps panel');
}

if (strpos($helperSource, 'Product gaps') === false) {
    pu_verify_fail('Product gaps panel copy missing in integration helper');
} else {
    pu_verify_pass('Product gaps heading present in integration helper');
}

$calendarSource = is_file($calendarPath) ? (string)file_get_contents($calendarPath) : '';
if (strpos($calendarSource, 'itm_patches_updates_list_calendar_rows') === false) {
    pu_verify_fail('calendar/index.php must use itm_patches_updates_list_calendar_rows()');
} else {
    pu_verify_pass('Calendar uses shared patch calendar helper');
}

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$count30 = itm_patches_updates_due_within_days_count($conn, $companyId, 30);
if ($count30 < 0) {
    pu_verify_fail('Due-within-30-days count must be non-negative');
} else {
    pu_verify_pass('Due-within-30-days metric is non-negative (' . $count30 . ')');
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$calendarRows = itm_patches_updates_list_calendar_rows($conn, $companyId, $monthStart, $monthEnd);
if (!is_array($calendarRows)) {
    pu_verify_fail('Calendar rows helper must return an array');
} else {
    pu_verify_pass('Calendar rows helper returns array (' . count($calendarRows) . ' row(s) this month)');
}

$summary = itm_patches_updates_integration_summary($conn, $companyId, 1);
if (!is_array($summary) || !array_key_exists('due_within_30_days', $summary)) {
    pu_verify_fail('Integration summary must include due_within_30_days');
} else {
    pu_verify_pass('Integration summary shape OK');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All patches integration checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
