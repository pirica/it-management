<?php
/**
 * Verifies per-request table-level caching in itm_table_has_column() and
 * itm_table_column_is_nullable() per-request table-level information_schema cache.
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
 * Why: Each measurement ends with one SHOW STATUS; subtract that query from the delta.
 *
 * @return array{pass:bool,delta:int,checks:int,note:string}
 */
function vmcc_measure_batch(
    mysqli $conn,
    string $table,
    array $columns,
    callable $fn,
    string $label,
    int &$questionsBaseline,
    bool $expectColdLoad
): array {
    foreach ($columns as $column) {
        $fn($conn, $table, $column);
    }

    $after = vmcc_session_questions($conn);
    if ($after === null) {
        return [
            'pass' => false,
            'delta' => -1,
            'checks' => count($columns),
            'note' => $label . ': unable to read Questions counter after batch',
        ];
    }

    // Why: The trailing SHOW STATUS increments Questions once; exclude it from batch cost.
    $delta = $after - $questionsBaseline - 1;
    $questionsBaseline = $after;

    $checks = count($columns);
    if ($expectColdLoad) {
        $logicalUncached = $checks;
        $logicalCached = 1;
        $reductionPct = $logicalUncached > 0
            ? round((1 - ($logicalCached / $logicalUncached)) * 100, 1)
            : 0.0;
        $pass = ($delta >= 1 && $delta <= 2);
        $note = sprintf(
            '%s: %d checks, schema Questions delta=%d (expect 1-2 for first table load), logical %d→1 (%.1f%% reduction)',
            $label,
            $checks,
            $delta,
            $logicalUncached,
            $reductionPct
        );
    } else {
        $pass = ($delta === 0);
        $note = sprintf(
            '%s: repeated %d checks, schema Questions delta=%d (expect 0 when cache is warm)',
            $label,
            $checks,
            $delta
        );
    }

    return ['pass' => $pass, 'delta' => $delta, 'checks' => $checks, 'note' => $note];
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

$questionsBaseline = vmcc_session_questions($conn);
if ($questionsBaseline === null) {
    echo colorText('[FAIL] Unable to read initial Questions counter.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$passAll = true;
$results = [];

$results[] = vmcc_measure_batch(
    $conn,
    $table,
    $switchPortColumns,
    'itm_table_has_column',
    'Cold itm_table_has_column',
    $questionsBaseline,
    true
);
$results[] = vmcc_measure_batch(
    $conn,
    $table,
    $switchPortColumns,
    'itm_table_has_column',
    'Warm itm_table_has_column',
    $questionsBaseline,
    false
);
$results[] = vmcc_measure_batch(
    $conn,
    $table,
    $nullableSampleColumns,
    'itm_table_column_is_nullable',
    'Cold itm_table_column_is_nullable',
    $questionsBaseline,
    true
);
$results[] = vmcc_measure_batch(
    $conn,
    $table,
    $nullableSampleColumns,
    'itm_table_column_is_nullable',
    'Warm itm_table_column_is_nullable',
    $questionsBaseline,
    false
);

foreach ($results as $result) {
    $passAll = $passAll && $result['pass'];
    $type = $result['pass'] ? 'pass' : 'fail';
    echo colorText('[' . strtoupper($type) . '] ' . $result['note'], $type) . $nl;
}

if ($passAll) {
    echo colorText(
        '[PASS] Table-level metadata cache verified. Cold schema delta 1-2 is normal (prepare+execute); warm repeat schema delta must stay 0.',
        'pass'
    ) . $nl;
} else {
    echo colorText(
        '[FAIL] Metadata cache contract not satisfied. See notes above; see includes/bootstrap_helpers.php and scripts/SCRIPTS.md.',
        'fail'
    ) . $nl;
}

itm_script_output_end();
exit($passAll ? 0 : 1);
