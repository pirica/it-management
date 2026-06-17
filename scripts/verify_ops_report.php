<?php
/**
 * Ops Report regression checks — D-2 edit lock and daily report creation.
 *
 * Browser: scripts/verify_ops_report.php
 * CLI: php scripts/verify_ops_report.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Ops Report Verification');

$nl = itm_script_output_nl();
$failures = 0;

function opr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function opr_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    opr_verify_fail('No database connection.');
    exit(1);
}

$companyId = 1;

// Why: Mirror module helper without loading full index.php (session/HTML).
function opr_verify_is_editable_date($dateStr, $isAdmin)
{
    if ($isAdmin) {
        return true;
    }
    if (!$dateStr) {
        return false;
    }
    $cutoff = date('Y-m-d', strtotime('-2 days'));
    return date('Y-m-d', strtotime($dateStr)) > $cutoff;
}

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

if (!opr_verify_is_editable_date($today, false)) {
    opr_verify_fail('Today should be editable for non-admin');
} else {
    opr_verify_pass('Today editable for non-admin');
}

if (!opr_verify_is_editable_date($yesterday, false)) {
    opr_verify_fail('Yesterday should be editable for non-admin');
} else {
    opr_verify_pass('Yesterday editable for non-admin');
}

if (opr_verify_is_editable_date($twoDaysAgo, false)) {
    opr_verify_fail('D-2 should be locked for non-admin');
} else {
    opr_verify_pass('D-2 locked for non-admin');
}

if (!opr_verify_is_editable_date($twoDaysAgo, true)) {
    opr_verify_fail('Admin should edit D-2');
} else {
    opr_verify_pass('Admin may edit D-2');
}

// Create + cleanup test report for today
$stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE company_id = ? AND report_date = ?');
mysqli_stmt_bind_param($stmt, 'is', $companyId, $today);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)');
$shift = 'MBQA verify shift';
mysqli_stmt_bind_param($stmt, 'iss', $companyId, $today, $shift);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert ops_report failed: ' . mysqli_error($conn));
} else {
    $reportId = (int)mysqli_insert_id($conn);
    opr_verify_pass('Inserted ops_report id=' . $reportId);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT id, today_shift FROM ops_report WHERE company_id = ? AND report_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $companyId, $today);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row || $row['today_shift'] !== $shift) {
    opr_verify_fail('ops_report read-back mismatch');
} else {
    opr_verify_pass('ops_report read-back OK');
}

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, guest_name, sort_order, active) VALUES (?, ?, ?, 0, 1)');
$guest = 'MBQA Guest';
mysqli_stmt_bind_param($stmt, 'iis', $companyId, $reportId, $guest);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert guest experience row failed');
} else {
    opr_verify_pass('Guest experience child row inserted');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE id = ? AND company_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $reportId, $companyId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM ops_report_guest_experience WHERE ops_report_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $reportId);
mysqli_stmt_execute($stmt);
$cnt = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

if ($cnt !== 0) {
    opr_verify_fail('Cascade delete did not remove child rows');
} else {
    opr_verify_pass('Cascade delete removed child rows');
}

// Table existence
$requiredTables = [
    'ops_report',
    'ops_report_fb_outlet',
    'ops_report_walk_round',
    'ops_report_courtesy_call',
    'ops_report_guest_experience',
    'ops_report_butler',
    'ops_report_night_shift',
];
foreach ($requiredTables as $table) {
    $safe = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$safe}'");
    if (!$res || mysqli_num_rows($res) === 0) {
        opr_verify_fail("Missing table {$table}");
    } else {
        opr_verify_pass("Table {$table} exists");
    }
}

$res = mysqli_query($conn, "SELECT id FROM modules_registry WHERE module_slug = 'ops_report' LIMIT 1");
if (!$res || !mysqli_fetch_assoc($res)) {
    opr_verify_fail('modules_registry missing ops_report');
} else {
    opr_verify_pass('modules_registry has ops_report');
}

if ($failures > 0) {
    echo colorText("{$failures} failure(s).", 'fail') . $nl;
    exit(1);
}

echo colorText('All ops_report checks passed.', 'pass') . $nl;
exit(0);
