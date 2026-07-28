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

$companyId = 1;
$settings = itm_appointment_load_settings($conn, $companyId);
if (!$settings) {
    appt_verify_fail('appointment_settings seed missing for company 1');
} else {
    appt_verify_pass('appointment_settings loaded for company 1');
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

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All appointment checks passed.', 'pass') . $nl;
exit(0);
