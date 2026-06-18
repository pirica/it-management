<?php
/**
 * Verifies per-request table-level caching in itm_table_has_column() and
 * itm_table_column_is_nullable() (docs/bolt.md metadata optimization).
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Metadata Column Cache Verification');

$nl = itm_script_output_nl();
echo 'Verifying information_schema column cache (bootstrap_helpers)...' . $nl;

/**
 * @return int|null
 */
function vmcc_session_questions(mysqli $conn): ?int
{
    $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_row($res);
    mysqli_free_result($res);

    return is_array($row) ? (int)$row[1] : null;
}

/**
 * @return array{pass:bool,delta:int,checks:int,note:string}
 */
function vmcc_run_has_column_batch(mysqli $conn, string $table, array $columns, string $label): array
{
    $before = vmcc_session_questions($conn);
    if ($before === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter'];
    }

    foreach ($columns as $column) {
        itm_table_has_column($conn, $table, $column);
    }

    $after = vmcc_session_questions($conn);
    if ($after === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter after batch'];
    }

    $delta = $after - $before;
    $checks = count($columns);
    $logicalUncached = $checks;
    $logicalCached = 1;
    $reductionPct = $logicalUncached > 0
        ? round((1 - ($logicalCached / $logicalUncached)) * 100, 1)
        : 0.0;

    // Why: MySQL may count prepare + execute as 2 Questions for one information_schema fetch.
    $pass = ($delta >= 1 && $delta <= 2);
    $note = sprintf(
        '%s: %d checks, Questions delta=%d (expect 1-2 for first table load), logical %d→1 (%.1f%% reduction)',
        $label,
        $checks,
        $delta,
        $logicalUncached,
        $reductionPct
    );

    return ['pass' => $pass, 'delta' => $delta, 'checks' => $checks, 'note' => $note];
}

/**
 * @return array{pass:bool,delta:int,checks:int,note:string}
 */
function vmcc_run_nullable_batch(mysqli $conn, string $table, array $columns, string $label): array
{
    $before = vmcc_session_questions($conn);
    if ($before === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter'];
    }

    foreach ($columns as $column) {
        itm_table_column_is_nullable($conn, $table, $column);
    }

    $after = vmcc_session_questions($conn);
    if ($after === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter after batch'];
    }

    $delta = $after - $before;
    $checks = count($columns);
    $pass = ($delta >= 1 && $delta <= 2);
    $note = sprintf(
        '%s: %d nullable checks, Questions delta=%d (expect 1-2 for first nullable table load)',
        $label,
        $checks,
        $delta
    );

    return ['pass' => $pass, 'delta' => $delta, 'checks' => $checks, 'note' => $note];
}

/**
 * @return array{pass:bool,delta:int,checks:int,note:string}
 */
function vmcc_run_warm_repeat(mysqli $conn, string $table, array $columns, callable $fn, string $label): array
{
    $before = vmcc_session_questions($conn);
    if ($before === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter'];
    }

    foreach ($columns as $column) {
        $fn($conn, $table, $column);
    }

    $after = vmcc_session_questions($conn);
    if ($after === null) {
        return ['pass' => false, 'delta' => -1, 'checks' => count($columns), 'note' => $label . ': unable to read Questions counter after repeat'];
    }

    $delta = $after - $before;
    $pass = ($delta === 0);
    $note = sprintf(
        '%s: repeated %d checks, Questions delta=%d (expect 0 when cache is warm)',
        $label,
        count($columns),
        $delta
    );

    return ['pass' => $pass, 'delta' => $delta, 'checks' => count($columns), 'note' => $note];
}

$table = (string)(getenv('ITM_META_CACHE_TABLE') ?: 'switch_ports');
if (!itm_is_safe_identifier($table)) {
    echo colorText('[FAIL] ITM_META_CACHE_TABLE must be a safe identifier.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

// Why: Same column set as includes/update_port.php schema compatibility probes.
$switchPortColumns = [
    'equipment_id',
    'status_id',
    'color_id',
    'rj45_speed_id',
    'vlan_id',
    'fiber_port_id',
    'fiber_patch_id',
    'fiber_rack_id',
    'to_idf_id',
    'rack_id',
    'to_rack_id',
    'to_location_id',
    'location_id',
    'hostname',
    'management_id',
];

$nullableSampleColumns = ['status_id', 'color_id', 'vlan_id', 'hostname', 'management_id'];

$passAll = true;
$results = [];

$coldHas = vmcc_run_has_column_batch($conn, $table, $switchPortColumns, 'Cold itm_table_has_column');
$results[] = $coldHas;
$passAll = $passAll && $coldHas['pass'];

$warmHas = vmcc_run_warm_repeat(
    $conn,
    $table,
    $switchPortColumns,
    'itm_table_has_column',
    'Warm itm_table_has_column'
);
$results[] = $warmHas;
$passAll = $passAll && $warmHas['pass'];

$coldNullable = vmcc_run_nullable_batch($conn, $table, $nullableSampleColumns, 'Cold itm_table_column_is_nullable');
$results[] = $coldNullable;
$passAll = $passAll && $coldNullable['pass'];

$warmNullable = vmcc_run_warm_repeat(
    $conn,
    $table,
    $nullableSampleColumns,
    'itm_table_column_is_nullable',
    'Warm itm_table_column_is_nullable'
);
$results[] = $warmNullable;
$passAll = $passAll && $warmNullable['pass'];

foreach ($results as $result) {
    $type = $result['pass'] ? 'pass' : 'fail';
    echo colorText('[' . strtoupper($type) . '] ' . $result['note'], $type) . $nl;
}

if ($passAll) {
    echo colorText(
        '[PASS] Table-level metadata cache verified. Questions delta 1-2 on cold load is normal (prepare+execute); warm repeat must stay 0.',
        'pass'
    ) . $nl;
} else {
    echo colorText(
        '[FAIL] Metadata cache contract not satisfied. See notes above; compare with docs/bolt.md.',
        'fail'
    ) . $nl;
}

itm_script_output_end();
exit($passAll ? 0 : 1);
