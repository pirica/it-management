<?php
/**
 * Equipment view related-module regression checks (patches + tickets).
 *
 * CLI: php scripts/verify_equipment_view_related.php
 * Browser: scripts/verify_equipment_view_related.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_equipment_view_related.php?run=1">verify_equipment_view_related.php?run=1</a>. CLI: <code>php scripts/verify_equipment_view_related.php</code>. Run when changing equipment view patch/ticket cards or related helpers.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_patches_updates_integrations.php';
require_once ROOT_PATH . 'includes/itm_equipment_view_related.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Equipment View Related Records Verification');

$nl = itm_script_output_nl();
$failures = 0;

function evr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function evr_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    evr_verify_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$equipmentViewPath = ROOT_PATH . 'modules/equipment/view.php';
$equipmentViewSource = is_file($equipmentViewPath) ? (string)file_get_contents($equipmentViewPath) : '';
$patchesHelperSource = is_file(ROOT_PATH . 'includes/itm_patches_updates_integrations.php')
    ? (string)file_get_contents(ROOT_PATH . 'includes/itm_patches_updates_integrations.php')
    : '';
$ticketsHelperSource = is_file(ROOT_PATH . 'includes/itm_equipment_view_related.php')
    ? (string)file_get_contents(ROOT_PATH . 'includes/itm_equipment_view_related.php')
    : '';

$requiredFunctions = [
    'itm_patches_updates_fetch_for_equipment',
    'itm_patches_updates_render_equipment_view_card',
    'itm_equipment_fetch_linked_tickets',
    'itm_equipment_render_tickets_view_card',
];
foreach ($requiredFunctions as $fn) {
    if (!function_exists($fn)) {
        evr_verify_fail('Missing helper ' . $fn . '()');
    } else {
        evr_verify_pass('Helper ' . $fn . '() loaded');
    }
}

if (strpos($equipmentViewSource, 'itm_patches_updates_render_equipment_view_card') === false) {
    evr_verify_fail('equipment/view.php must render patches card');
} else {
    evr_verify_pass('Equipment view renders patches card');
}

if (strpos($equipmentViewSource, 'itm_equipment_render_tickets_view_card') === false) {
    evr_verify_fail('equipment/view.php must render tickets card');
} else {
    evr_verify_pass('Equipment view renders tickets card');
}

if (strpos($patchesHelperSource, '$dashboardAllowed = !empty($summary[\'dashboard_allowed\']);') === false) {
    evr_verify_fail('Product gaps panel must assign dashboardAllowed from integration summary');
} else {
    evr_verify_pass('Product gaps panel assigns dashboardAllowed');
}

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$equipmentRes = mysqli_query(
    $conn,
    'SELECT id FROM equipment WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
);
$equipmentId = 0;
if ($equipmentRes && ($equipmentRow = mysqli_fetch_assoc($equipmentRes))) {
    $equipmentId = (int)($equipmentRow['id'] ?? 0);
}

if ($equipmentId <= 0) {
    evr_verify_fail('No equipment row available for tenant probe');
} else {
    evr_verify_pass('Equipment probe row id=' . $equipmentId);
    $patchRow = itm_patches_updates_fetch_for_equipment($conn, $companyId, $equipmentId);
    if ($patchRow !== null && !is_array($patchRow)) {
        evr_verify_fail('Patch fetch helper must return array or null');
    } else {
        evr_verify_pass('Patch fetch helper returns ' . ($patchRow ? 'linked row' : 'null'));
    }
    $ticketRows = itm_equipment_fetch_linked_tickets($conn, $companyId, $equipmentId, 5);
    if (!is_array($ticketRows)) {
        evr_verify_fail('Ticket fetch helper must return array');
    } else {
        evr_verify_pass('Ticket fetch helper returns array (' . count($ticketRows) . ' row(s))');
    }
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All equipment view related-record checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
