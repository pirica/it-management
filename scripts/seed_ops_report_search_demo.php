<?php
/**
 * Seed Ops Report cross-date search demo rows for verify + screenshot scripts.
 *
 * CLI: php scripts/seed_ops_report_search_demo.php [--company=1] [--keyword=DemoManager]
 * Browser: http://localhost/it-management/scripts/seed_ops_report_search_demo.php (Admin session)
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_ops_report_search.php';

if (!$itmIsCli) {
    itm_script_require_admin_script_or_exit($conn);
}

itm_script_output_begin('Ops Report Search Demo Seed');
$nl = itm_script_output_nl();

if (!$itmIsCli) {
    itm_script_output_close_pre();
    echo '<h1>Ops Report Search Demo Seed</h1>';
}

$options = $itmIsCli ? getopt('', ['company:', 'keyword:']) : ($_GET + $_POST);
$companyId = isset($options['company']) ? (int)$options['company'] : 1;
$keyword = trim((string)($options['keyword'] ?? 'DemoManager'));
if ($keyword === '') {
    $keyword = 'DemoManager';
}

$reportDateHeader = date('Y-m-d', strtotime('-8 days'));
$reportDateGuest = date('Y-m-d', strtotime('-15 days'));

function opr_seed_delete_report_by_date($conn, $companyId, $reportDate) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE company_id = ? AND report_date = ?');
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $reportDate);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function opr_seed_insert_report($conn, $companyId, $reportDate, $todayShift) {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)'
    );
    mysqli_stmt_bind_param($stmt, 'iss', $companyId, $reportDate, $todayShift);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function opr_seed_insert_guest_row($conn, $companyId, $reportId, $guestName) {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, guest_name, sort_order, active) VALUES (?, ?, ?, 0, 1)'
    );
    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $reportId, $guestName);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

opr_seed_delete_report_by_date($conn, $companyId, $reportDateHeader);
opr_seed_delete_report_by_date($conn, $companyId, $reportDateGuest);

$headerReportId = opr_seed_insert_report(
    $conn,
    $companyId,
    $reportDateHeader,
    'Morning ' . $keyword . ' duty'
);
$guestReportId = opr_seed_insert_report($conn, $companyId, $reportDateGuest, 'Night shift');
$guestOk = $guestReportId > 0 && opr_seed_insert_guest_row($conn, $companyId, $guestReportId, $keyword . ' Guest');

if ($headerReportId <= 0 || !$guestOk) {
    echo colorText('[FAIL] Demo seed insert failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$hits = opr_search_cross_date_hits($conn, $companyId, $keyword, 'all');
$lines = [];
foreach ($hits as $hitDate => $sections) {
    $lines[] = opr_search_cross_date_hit_line_text($keyword, $hitDate, $sections);
}

$today = (int)date('j');
$month = (int)date('n');
$year = (int)date('Y');
$demoPath = opr_report_index_url($today, $month, $year, $keyword, 'all', 'all');
$demoUrl = '/it-management/modules/ops_report/' . $demoPath;

echo colorText('[PASS] Seeded Ops Report search demo for company ' . $companyId . '.', 'pass') . $nl;
echo 'Keyword: ' . $keyword . $nl;
echo 'Header report date: ' . $reportDateHeader . $nl;
echo 'Guest report date: ' . $reportDateGuest . $nl;
echo 'Demo URL: http://localhost' . $demoUrl . $nl;
echo 'Hit lines:' . $nl;
foreach ($lines as $line) {
    echo '  - ' . $line . $nl;
}

itm_script_output_end();
