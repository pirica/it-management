<?php
/**
 * Appointment module regression checks.
 *
 * CLI: php scripts/verify_appointment.php
 * Browser: scripts/verify_appointment.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_appointment.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/appointment/</code>, <code>includes/itm_appointment.php</code>, or appointment tables in <code>db/</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Appointment Verification');

$nl = itm_script_output_nl();
$failures = 0;

function appt_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function appt_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$tables = ['appointment_visit_reasons', 'appointment_settings', 'appointment_business_hours', 'appointment_type', 'appointments'];
foreach ($tables as $table) {
    $sql = "SELECT COUNT(*) AS c FROM information_schema.triggers
            WHERE trigger_schema = DATABASE() AND event_object_table = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $count = (int)($row['c'] ?? 0);
    if ($count < 3) {
        appt_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
    } else {
        appt_verify_pass("Audit triggers present for {$table}");
    }
}

$idxSql = "SELECT COUNT(*) AS c FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'appointments'
             AND index_name = 'uq_appointments_company_booking_lock'";
$idxRes = mysqli_query($conn, $idxSql);
$idxRow = $idxRes ? mysqli_fetch_assoc($idxRes) : null;
if ((int)($idxRow['c'] ?? 0) < 1) {
    appt_verify_fail('Missing uq_appointments_company_booking_lock on appointments');
} else {
    appt_verify_pass('appointments booking_lock unique index present');
}

$colSql = "SELECT COUNT(*) AS c FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'appointments'
             AND column_name IN ('assigned_to_employee_id', 'is_confirmed')";
$colRes = mysqli_query($conn, $colSql);
$colRow = $colRes ? mysqli_fetch_assoc($colRes) : null;
if ((int)($colRow['c'] ?? 0) < 2) {
    appt_verify_fail('appointments missing assigned_to_employee_id or is_confirmed columns');
} else {
    appt_verify_pass('appointments assignee and confirmed columns present');
}

$companyId = 1;
$settings = itm_appointment_load_settings($conn, $companyId);
if (!$settings) {
    appt_verify_fail('appointment_settings seed missing for company 1');
} else {
    appt_verify_pass('appointment_settings loaded for company 1');
}

$modColSql = "SELECT COUNT(*) AS c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'appointment_settings'
                AND column_name = 'default_appointment_modality'";
$modColRes = mysqli_query($conn, $modColSql);
$modColRow = $modColRes ? mysqli_fetch_assoc($modColRes) : null;
if ((int)($modColRow['c'] ?? 0) < 1) {
    appt_verify_fail('appointment_settings missing default_appointment_modality column');
} else {
    appt_verify_pass('appointment_settings default_appointment_modality column present');
}

$reasons = itm_appointment_load_visit_reasons($conn, $companyId);
if (count($reasons) < 1) {
    appt_verify_fail('appointment_visit_reasons seed missing for company 1');
} else {
    appt_verify_pass('appointment_visit_reasons seeded');
}

$types = itm_appointment_load_appointment_types($conn, $companyId);
$typeNames = array_column($types, 'name');
if (!in_array('in_person', $typeNames, true) || !in_array('remote', $typeNames, true)) {
    appt_verify_fail('appointment_type seeds missing in_person or remote for company 1');
} else {
    appt_verify_pass('appointment_type in_person and remote seeded');
}

$week = itm_appointment_build_week_slots($conn, $companyId, date('Y-m-d'));
$bookableDays = 0;
foreach ($week['days'] as $day) {
    if (!empty($day['slots'])) {
        $bookableDays++;
    }
    if (!array_key_exists('allows_in_person', $day) || !array_key_exists('allows_remote', $day)) {
        appt_verify_fail('week_slots day missing allows_in_person / allows_remote');
        break;
    }
    if (!array_key_exists('allowed_types', $day) || !is_array($day['allowed_types'])) {
        appt_verify_fail('week_slots day missing allowed_types map');
        break;
    }
}
if ($bookableDays < 1) {
    appt_verify_fail('No bookable slots generated for current week');
} else {
    appt_verify_pass('Slot builder returns bookable days');
}

$slugStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? AND active = 1 LIMIT 1');
$slug = 'appointment';
mysqli_stmt_bind_param($slugStmt, 's', $slug);
mysqli_stmt_execute($slugStmt);
$slugRes = mysqli_stmt_get_result($slugStmt);
$slugRow = $slugRes ? mysqli_fetch_assoc($slugRes) : null;
mysqli_stmt_close($slugStmt);
if (!$slugRow) {
    appt_verify_fail('modules_registry row missing for appointment');
} else {
    appt_verify_pass('modules_registry row for appointment');
}

$settingsSlug = 'appointment_settings';
$settingsSlugStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? AND active = 1 LIMIT 1');
mysqli_stmt_bind_param($settingsSlugStmt, 's', $settingsSlug);
mysqli_stmt_execute($settingsSlugStmt);
$slugRes = mysqli_stmt_get_result($settingsSlugStmt);
$slugRow = $slugRes ? mysqli_fetch_assoc($slugRes) : null;
mysqli_stmt_close($settingsSlugStmt);
if (!$slugRow) {
    appt_verify_fail('modules_registry row missing for appointment_settings');
} else {
    appt_verify_pass('modules_registry row for appointment_settings');
}

$settingsModulePath = ROOT_PATH . 'modules/appointment_settings/index.php';
if (!is_file($settingsModulePath)) {
    appt_verify_fail('modules/appointment_settings/index.php missing');
} else {
    appt_verify_pass('appointment_settings module entry present');
}

require_once ROOT_PATH . 'includes/itm_appointment_settings_admin.php';
itm_appointment_settings_ensure_company_config($conn, $companyId, 1);
$settingsAfterEnsure = itm_appointment_load_settings($conn, $companyId);
if (!$settingsAfterEnsure) {
    appt_verify_fail('itm_appointment_settings_ensure_company_config did not create settings');
} else {
    appt_verify_pass('appointment_settings ensure helper creates tenant row');
}

$modalitySampleErrors = itm_appointment_regression_collect_company_modality_sample_errors($conn, $companyId);
if ($modalitySampleErrors === []) {
    appt_verify_pass('Company 1 modality sample (Mon/Tue/Thu/Fri both, Wed remote-only) matches seeds');
} else {
    foreach ($modalitySampleErrors as $modalityError) {
        appt_verify_fail($modalityError);
    }
    appt_verify_fail('Align company 1 with db/02_data.sql business hours or re-import; see itm_appointment_regression_sample_business_hours_by_dow()');
}

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All appointment checks passed.', 'pass') . $nl;
exit(0);
