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

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, today_shift, report_ui_json, active) VALUES (?, ?, ?, ?, 1)');
$shift = 'MBQA verify shift';
$uiSeed = json_encode(['page_title' => 'Daily Operations Report', 'subtitle' => 'Test subtitle'], JSON_UNESCAPED_UNICODE);
mysqli_stmt_bind_param($stmt, 'isss', $companyId, $today, $shift, $uiSeed);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert ops_report failed: ' . mysqli_error($conn));
} else {
    $reportId = (int)mysqli_insert_id($conn);
    opr_verify_pass('Inserted ops_report id=' . $reportId);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT id, today_shift, report_ui_json FROM ops_report WHERE company_id = ? AND report_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $companyId, $today);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row || $row['today_shift'] !== $shift) {
    opr_verify_fail('ops_report read-back mismatch');
} else {
    opr_verify_pass('ops_report read-back OK');
}

$decodedUi = json_decode((string)($row['report_ui_json'] ?? ''), true);
if (!is_array($decodedUi) || ($decodedUi['subtitle'] ?? '') !== 'Test subtitle') {
    opr_verify_fail('report_ui_json read-back mismatch');
} else {
    opr_verify_pass('report_ui_json read-back OK');
}

$res = mysqli_query($conn, "SHOW COLUMNS FROM ops_report LIKE 'report_ui_json'");
if (!$res || mysqli_num_rows($res) === 0) {
    opr_verify_fail('ops_report.report_ui_json column missing');
} else {
    opr_verify_pass('ops_report.report_ui_json column exists');
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

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_hotel_figure (company_id, ops_report_id, field_label, field_value, sort_order, active) VALUES (?, ?, ?, ?, 0, 1)');
$figureLabel = 'MBQA Custom Metric';
$figureValue = '42';
mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $reportId, $figureLabel, $figureValue);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert hotel figure row failed');
} else {
    $figureId = (int)mysqli_insert_id($conn);
    opr_verify_pass('Hotel figure child row inserted id=' . $figureId);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT field_label, field_value FROM ops_report_hotel_figure WHERE id = ? AND ops_report_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $figureId, $reportId);
mysqli_stmt_execute($stmt);
$figureRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$figureRow || $figureRow['field_label'] !== $figureLabel || $figureRow['field_value'] !== $figureValue) {
    opr_verify_fail('ops_report_hotel_figure read-back mismatch');
} else {
    opr_verify_pass('ops_report_hotel_figure read-back OK');
}

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
    opr_verify_fail('Cascade delete did not remove guest experience child rows');
} else {
    opr_verify_pass('Cascade delete removed guest experience child rows');
}

$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM ops_report_hotel_figure WHERE ops_report_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $reportId);
mysqli_stmt_execute($stmt);
$figureCnt = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

if ($figureCnt !== 0) {
    opr_verify_fail('Cascade delete did not remove hotel figure child rows');
} else {
    opr_verify_pass('Cascade delete removed hotel figure child rows');
}

// Cross-date search: unique guest on a past report_date must be discoverable via child-table JOIN.
$pastDate = date('Y-m-d', strtotime('-5 days'));
$crossDateToken = 'MBQA-CROSSDATE-' . substr(md5((string)mt_rand()), 0, 8);

$stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE company_id = ? AND report_date = ?');
mysqli_stmt_bind_param($stmt, 'is', $companyId, $pastDate);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)');
$pastShift = 'MBQA past shift';
mysqli_stmt_bind_param($stmt, 'iss', $companyId, $pastDate, $pastShift);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert past ops_report for cross-date search failed: ' . mysqli_error($conn));
} else {
    $pastReportId = (int)mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, guest_name, sort_order, active) VALUES (?, ?, ?, 0, 1)');
mysqli_stmt_bind_param($stmt, 'iis', $companyId, $pastReportId, $crossDateToken);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert past guest experience row for cross-date search failed');
} else {
    opr_verify_pass('Past guest experience row inserted for cross-date search');
}
mysqli_stmt_close($stmt);

$crossPattern = '%' . $crossDateToken . '%';
$stmt = mysqli_prepare(
    $conn,
    'SELECT DISTINCT r.report_date FROM ops_report r'
    . ' INNER JOIN ops_report_guest_experience c ON c.ops_report_id = r.id AND c.company_id = r.company_id'
    . ' WHERE r.company_id = ? AND r.active = 1 AND c.active = 1'
    . ' AND CONCAT_WS(\' \', COALESCE(c.ref_id,\'\'), COALESCE(c.guest_name,\'\'), COALESCE(c.room_number,\'\'), COALESCE(c.time_reported,\'\'), COALESCE(c.checkout_date,\'\'), COALESCE(c.feedback,\'\'), COALESCE(c.action_taken,\'\'), COALESCE(c.case_closed,\'\'), COALESCE(c.monitor,\'\')) LIKE ?'
);
mysqli_stmt_bind_param($stmt, 'is', $companyId, $crossPattern);
mysqli_stmt_execute($stmt);
$crossHit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$crossHit || ($crossHit['report_date'] ?? '') !== $pastDate) {
    opr_verify_fail('Cross-date search did not return past report_date for guest token');
} else {
    opr_verify_pass('Cross-date search finds past report_date via guest experience child row');
}

require_once __DIR__ . '/lib/itm_ops_report_search.php';

$sampleHits = [
    '2026-01-10' => ['Guest Experience Report'],
    '2026-01-20' => ['Duty Managers / Hotel Figures'],
    '2026-01-15' => ['Courtesy Calls'],
];
$sampleRows = opr_search_cross_date_rows_from_hits($sampleHits);
$sortedAsc = opr_search_cross_date_sort_rows($sampleRows, 'report_date', 'ASC');
if (($sortedAsc[0]['report_date'] ?? '') !== '2026-01-10' || ($sortedAsc[2]['report_date'] ?? '') !== '2026-01-20') {
    opr_verify_fail('Cross-date search sort ASC report_date expected oldest-to-newest order');
} else {
    opr_verify_pass('Cross-date search sort ASC report_date');
}

$sortedSections = opr_search_cross_date_sort_rows($sampleRows, 'sections', 'ASC');
if (($sortedSections[0]['sections_label'] ?? '') !== 'Courtesy Calls') {
    opr_verify_fail('Cross-date search sort ASC sections expected alphabetical section labels');
} else {
    opr_verify_pass('Cross-date search sort ASC sections');
}

$paginated = opr_search_cross_date_paginate_rows($sortedAsc, 2, 1);
if (count($paginated['rows']) !== 1 || ($paginated['rows'][0]['report_date'] ?? '') !== '2026-01-15' || (int)$paginated['total'] !== 3) {
    opr_verify_fail('Cross-date search pagination page/perPage mismatch');
} else {
    opr_verify_pass('Cross-date search pagination honours page and per-page size');
}

$stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE id = ? AND company_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $pastReportId, $companyId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

require_once __DIR__ . '/lib/itm_ops_report_search.php';

// Cross-date hit list: keyword | linked day — section labels (multi-date sample data).
$hitKeyword = 'MBQA-HITLINE-' . substr(md5((string)mt_rand()), 0, 6);
$hitDateHeader = date('Y-m-d', strtotime('-9 days'));
$hitDateGuest = date('Y-m-d', strtotime('-14 days'));
$sectionLabels = opr_search_cross_date_section_labels();

foreach ([$hitDateHeader, $hitDateGuest] as $cleanupDate) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE company_id = ? AND report_date = ?');
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $cleanupDate);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)');
$headerShift = 'Shift ' . $hitKeyword;
mysqli_stmt_bind_param($stmt, 'iss', $companyId, $hitDateHeader, $headerShift);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert header ops_report for hit-line verify failed: ' . mysqli_error($conn));
} else {
    $hitHeaderReportId = (int)mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)');
$guestShift = 'Guest day';
mysqli_stmt_bind_param($stmt, 'iss', $companyId, $hitDateGuest, $guestShift);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert guest ops_report for hit-line verify failed: ' . mysqli_error($conn));
} else {
    $hitGuestReportId = (int)mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt);

$guestName = $hitKeyword . ' Smith';
$stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, guest_name, sort_order, active) VALUES (?, ?, ?, 0, 1)');
mysqli_stmt_bind_param($stmt, 'iis', $companyId, $hitGuestReportId, $guestName);
if (!mysqli_stmt_execute($stmt)) {
    opr_verify_fail('Insert guest experience row for hit-line verify failed');
}
mysqli_stmt_close($stmt);

$hitMap = opr_search_cross_date_hits($conn, $companyId, $hitKeyword, 'all');
if (count($hitMap) < 2) {
    opr_verify_fail('Cross-date hit map expected at least two report_date keys for hit-line verify');
} else {
    opr_verify_pass('Cross-date hit map returns multiple report dates for shared keyword');
}

$expectedHeaderLine = opr_search_cross_date_hit_line_text($hitKeyword, $hitDateHeader, [$sectionLabels['report']]);
$expectedGuestLine = opr_search_cross_date_hit_line_text($hitKeyword, $hitDateGuest, [$sectionLabels['guest_experience']]);
$actualHeaderLine = isset($hitMap[$hitDateHeader])
    ? opr_search_cross_date_hit_line_text($hitKeyword, $hitDateHeader, $hitMap[$hitDateHeader])
    : '';
$actualGuestLine = isset($hitMap[$hitDateGuest])
    ? opr_search_cross_date_hit_line_text($hitKeyword, $hitDateGuest, $hitMap[$hitDateGuest])
    : '';

if ($actualHeaderLine !== $expectedHeaderLine) {
    opr_verify_fail('Hit line mismatch for header report (expected: ' . $expectedHeaderLine . ')');
} else {
    opr_verify_pass('Hit line format matches keyword | day — sections for header report');
}

if ($actualGuestLine !== $expectedGuestLine) {
    opr_verify_fail('Hit line mismatch for guest report (expected: ' . $expectedGuestLine . ')');
} else {
    opr_verify_pass('Hit line format matches keyword | day — sections for guest experience');
}

foreach ([$hitHeaderReportId ?? 0, $hitGuestReportId ?? 0] as $cleanupId) {
    if ($cleanupId <= 0) {
        continue;
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE id = ? AND company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $cleanupId, $companyId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
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
    'ops_report_hotel_figure',
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

$auditTables = [
    'ops_report',
    'ops_report_fb_outlet',
    'ops_report_walk_round',
    'ops_report_courtesy_call',
    'ops_report_guest_experience',
    'ops_report_butler',
    'ops_report_night_shift',
    'ops_report_hotel_figure',
];
foreach ($auditTables as $table) {
    $safeTable = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = '{$safeTable}' AND TRIGGER_NAME LIKE 'trg\\_%\\_audit\\_%'"
    );
    $count = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
    if ($count < 3) {
        opr_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
    } else {
        opr_verify_pass("Audit triggers present for {$table}");
    }
}

if ($failures > 0) {
    echo colorText("{$failures} failure(s).", 'fail') . $nl;
    exit(1);
}

echo colorText('All ops_report checks passed.', 'pass') . $nl;
exit(0);

itm_script_output_end();
