<?php
/**
 * Appointment module regression checks.
 */
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';

$failures = 0;

function appt_verify_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function appt_verify_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

$tables = ['appointment_visit_reasons', 'appointment_settings', 'appointment_business_hours', 'appointments'];
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

exit($failures > 0 ? 1 : 0);
