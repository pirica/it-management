<?php
/**
 * CLI: php scripts/verify_ops_report_sample_data.php
 * Verifies Add sample data on empty tenants for all ops_report_* child modules.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Ops Report child sample data verification');

$failures = 0;

function vorsd_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function vorsd_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    vorsd_fail('Database connection unavailable.');
    exit(1);
}

$companyId = 4;
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = $companyId;

$childTables = [
    'ops_report_butler',
    'ops_report_courtesy_call',
    'ops_report_fb_outlet',
    'ops_report_guest_experience',
    'ops_report_hotel_figure',
    'ops_report_night_shift',
    'ops_report_walk_round',
];

$allTables = array_merge(['ops_report'], $childTables);

foreach ($allTables as $table) {
    if (!itm_is_safe_identifier($table)) {
        vorsd_fail('Unsafe table name: ' . $table);
        continue;
    }
    mysqli_query($conn, 'DELETE FROM `' . $table . '` WHERE company_id = ' . (int)$companyId);
}

foreach ($childTables as $table) {
    $err = '';
    $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $err);
    $countRes = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS total FROM `' . $table . '` WHERE company_id = ' . (int)$companyId
        . (function_exists('itm_table_has_column') && itm_table_has_column($conn, $table, 'deleted_at')
            ? ' AND deleted_at IS NULL' : '')
    );
    $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
    $liveRows = (int)($countRow['total'] ?? 0);

    if ($liveRows < 1) {
        vorsd_fail($table . ' — expected at least one row; inserted=' . (int)$inserted . ' err=' . $err);
        continue;
    }

    vorsd_pass($table . ' — sample seed inserted ' . $liveRows . ' row(s).');
}

$parentRes = mysqli_query(
    $conn,
    'SELECT COUNT(*) AS total FROM ops_report WHERE company_id = ' . (int)$companyId
    . (function_exists('itm_table_has_column') && itm_table_has_column($conn, 'ops_report', 'deleted_at')
        ? ' AND deleted_at IS NULL' : '')
);
$parentRow = $parentRes ? mysqli_fetch_assoc($parentRes) : null;
$parentCount = (int)($parentRow['total'] ?? 0);
if ($parentCount < 1) {
    vorsd_fail('ops_report parent rows missing after child sample seed.');
} else {
    vorsd_pass('ops_report parent rows present (' . $parentCount . ').');
}

exit($failures === 0 ? 0 : 1);
