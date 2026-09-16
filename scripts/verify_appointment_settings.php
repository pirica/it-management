<?php
/**
 * Appointment Settings module regression checks.
 *
 * CLI: php scripts/verify_appointment_settings.php
 * Browser: scripts/verify_appointment_settings.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_appointment_settings.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/appointment_settings/</code>, <code>includes/itm_appointment_settings_admin.php</code>, or appointment settings admin UX (bulk hours grid, visit-reason reorder, flash, delete guards).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';
require_once ROOT_PATH . 'includes/itm_appointment_settings_admin.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Appointment Settings Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;

function aps_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function aps_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    aps_verify_fail('No database connection.');
    exit(1);
}

$helpers = [
    'itm_appointment_settings_visit_reason_name_exists',
    'itm_appointment_settings_save_business_hours_bulk',
    'itm_appointment_settings_reorder_visit_reasons',
    'appt_user_can_access_settings',
];
foreach ($helpers as $helper) {
    if (!function_exists($helper)) {
        aps_verify_fail('Missing helper ' . $helper . '()');
    } else {
        aps_verify_pass($helper . '() loaded');
    }
}

$indexPath = ROOT_PATH . 'modules/appointment_settings/index.php';
$indexCode = is_file($indexPath) ? (string)file_get_contents($indexPath) : '';
if ($indexCode === '') {
    aps_verify_fail('modules/appointment_settings/index.php missing');
} else {
    if (strpos($indexCode, 'bulk_business_hours') === false || strpos($indexCode, 'id="hours-grid"') === false) {
        aps_verify_fail('index.php missing weekly bulk hours grid');
    } else {
        aps_verify_pass('index.php includes weekly bulk hours grid');
    }
    if (strpos($indexCode, 'Delete company appointment settings?') !== false) {
        aps_verify_fail('index.php still exposes settings row delete');
    } else {
        aps_verify_pass('index.php hides settings row delete');
    }
    if (strpos($indexCode, 'aps_flash_render') === false) {
        aps_verify_fail('index.php missing session flash render');
    } else {
        aps_verify_pass('index.php uses session flash banner');
    }
}

$listPath = ROOT_PATH . 'modules/appointment_settings/list_all.php';
$listCode = is_file($listPath) ? (string)file_get_contents($listPath) : '';
if ($listCode === '') {
    aps_verify_fail('modules/appointment_settings/list_all.php missing');
} else {
    if (strpos($listCode, 'visit_reason_reorder') === false || strpos($listCode, 'appointment-settings.js') === false) {
        aps_verify_fail('list_all.php missing drag-sort reorder wiring');
    } else {
        aps_verify_pass('list_all.php wires visit-reason drag reorder');
    }
}

$deletePath = ROOT_PATH . 'modules/appointment_settings/delete.php';
$deleteCode = is_file($deletePath) ? (string)file_get_contents($deletePath) : '';
if (strpos($deleteCode, "kind === 'settings'") === false) {
    aps_verify_fail('delete.php missing settings delete guard');
} else {
    aps_verify_pass('delete.php blocks settings row delete');
}

$uniqueSql = "SELECT COUNT(*) AS c FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'appointment_visit_reasons'
                AND index_name = 'uq_appointment_visit_reasons_company_name'";
$uniqueRes = mysqli_query($conn, $uniqueSql);
$uniqueRow = $uniqueRes ? mysqli_fetch_assoc($uniqueRes) : null;
if ((int)($uniqueRow['c'] ?? 0) < 1) {
    aps_verify_fail('Missing uq_appointment_visit_reasons_company_name unique index');
} else {
    aps_verify_pass('appointment_visit_reasons company+name unique index present');
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'appointment_settings';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    $hasRow = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRow) {
        aps_verify_fail('modules_registry missing appointment_settings');
    } else {
        aps_verify_pass('modules_registry has appointment_settings');
    }
}

itm_appointment_settings_ensure_company_config($conn, $companyId, 1);
$reasons = itm_appointment_settings_load_visit_reasons_admin($conn, $companyId);
if ($reasons === []) {
    aps_verify_fail('No visit reasons loaded for company 1 after ensure');
} else {
    aps_verify_pass('Visit reasons admin loader returns rows for company 1');
}

$hours = itm_appointment_load_business_hours($conn, $companyId);
if (count($hours) < 7) {
    aps_verify_fail('Expected seven business hour rows for company 1');
} else {
    aps_verify_pass('Seven business hour rows present for company 1');
}

if ($failures > 0) {
    echo colorText($failures . ' check(s) failed.', 'fail') . $nl;
    exit(1);
}

echo colorText('All appointment settings checks passed.', 'pass') . $nl;
exit(0);
