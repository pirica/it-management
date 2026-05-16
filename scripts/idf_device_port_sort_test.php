<?php

/**
 * Deterministic proof for IDF device port sorting:
 * RJ45-family rows always ORDER before fiber (SFP%) for every primary sort column.
 *
 * Sections:
 * - Source wiring: confirms modules/idfs/device.php binds ORDER BY via shared helpers.
 * - Helper literals: verifies ORDER snippet shape (fiber rank ASC first, DESC only on primary column).
 * - MySQL synthetic: requires a reachable DB — proves server applies copper-before-fiber with duplicate port_no.
 * - MySQL duplex (when data exists): same ORDER BY as device.php on a position that has copper+fiber sharing port_no;
 *   verifies fiber_rank is monotone non-decreasing and port_no is non-decreasing within each fiber rank.
 *
 * Usage (CLI):
 *   php scripts/idf_device_port_sort_test.php [--offline-only]
 *
 * --offline-only: skip MySQL synthetic + duplex (still requires device.php wiring + helper assertions).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/idf_device_port_sort_sql.php';

function itmdf_sort_test_fail(string $msg): void
{
    fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
    exit(1);
}

function itmdf_sort_test_pass(string $msg): void
{
    echo '[PASS] ' . $msg . PHP_EOL;
}

function itmdf_sort_test_assert_true(bool $condition, string $msg): void
{
    if (!$condition) {
        itmdf_sort_test_fail($msg);
    }
    itmdf_sort_test_pass($msg);
}

$offlineOnly = false;
if (PHP_SAPI === 'cli') {
    $argvLocal = $argv ?? [];
    $offlineOnly = in_array('--offline-only', $argvLocal, true);
}

echo "idf_device_port_sort_test\n";

$devicePhp = dirname(__DIR__) . '/modules/idfs/device.php';
itmdf_sort_test_assert_true(is_readable($devicePhp), 'modules/idfs/device.php is readable');
$deviceSource = file_get_contents($devicePhp);
itmdf_sort_test_assert_true(
    $deviceSource !== false && strpos($deviceSource, 'includes/idf_device_port_sort_sql.php') !== false,
    'device.php requires includes/idf_device_port_sort_sql.php'
);
itmdf_sort_test_assert_true(
    strpos($deviceSource, 'itm_idf_ports_device_list_order_sql(') !== false,
    'device.php constructs ORDER BY via itm_idf_ports_device_list_order_sql()'
);
itmdf_sort_test_assert_true(
    strpos($deviceSource, 'ORDER BY " . $portOrderSql') !== false,
    'device.php binds $portOrderSql into the ports SELECT ORDER BY clause'
);

$rankSql = itm_idf_port_fiber_family_rank_sql();
itmdf_sort_test_assert_true(
    strpos($rankSql, 'sfp') !== false,
    'Fiber rank SQL references sfp% normalization'
);

$expectedPrefixAsc = $rankSql . ' ASC, pr.port_no ASC';
$orderAsc = itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'ASC');
itmdf_sort_test_assert_true(
    strpos($orderAsc, $expectedPrefixAsc) === 0,
    'ORDER BY for sort=port_no ASC begins with fiber rank ASC then pr.port_no ASC'
);

$expectedDesc = $rankSql . ' ASC, pr.port_no DESC, pr.port_no ASC';
itmdf_sort_test_assert_true(
    itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'DESC') === $expectedDesc,
    'DESC direction applies only to the primary ORDER column (fiber rank stays ASC first)'
);
itmdf_sort_test_assert_true(
    itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'desc') === $expectedDesc,
    'Lowercase desc normalized to DESC for primary ORDER column'
);

// Every supported sort expression must remain after fiber_rank ASC (not only port_type).
$orderTypeDesc = itm_idf_ports_device_list_order_sql($rankSql, itm_idf_port_type_label_sql(), 'DESC');
itmdf_sort_test_assert_true(
    strpos($orderTypeDesc, $rankSql . ' ASC, ') === 0,
    'sort=port_type DESC still prefixes fiber_rank ASC'
);

if ($offlineOnly) {
    echo "[DONE] Offline-only assertions complete (skipped MySQL proof blocks).\n";
    exit(0);
}

$host = getenv('ITM_DB_HOST');
if ($host === false || $host === '') {
    $host = '127.0.0.1';
}
$user = getenv('ITM_DB_USER');
if ($user === false || $user === '') {
    $user = 'root';
}
$passRaw = getenv('ITM_DB_PASS');
if ($passRaw === false) {
    $passRaw = 'itmanagement';
}
$name = getenv('ITM_DB_NAME');
if ($name === false || $name === '') {
    $name = 'itmanagement';
}

$db = @mysqli_connect((string)$host, (string)$user, (string)$passRaw, (string)$name);
if (!$db || mysqli_connect_errno() !== 0) {
    itmdf_sort_test_fail('MySQL unreachable — cannot execute synthetic ORDER proof (set ITM_DB_* or fix local DB).');
}

mysqli_set_charset($db, 'utf8mb4');

$sqlSynth =
    'SELECT port_no AS n, lbl
     FROM (
         SELECT 1 AS port_no, \'RJ45\' AS lbl UNION ALL
         SELECT 1 AS port_no, \' SFP \' AS lbl UNION ALL
         SELECT 2 AS port_no, \'RJ45\' AS lbl UNION ALL
         SELECT 3 AS port_no, \'SFP+\' AS lbl
     ) AS v
     ORDER BY CASE
         WHEN LOWER(REPLACE(REPLACE(TRIM(v.lbl), \' \', \'\'), \'+\', \'plus\')) LIKE \'sfp%\' THEN 1
         ELSE 0
       END ASC,
       v.port_no ASC';

$res = mysqli_query($db, $sqlSynth);
itmdf_sort_test_assert_true((bool)$res, 'Synthetic UNION ORDER BY query executed');

$got = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $got[] = [(int)$row['n'], trim((string)$row['lbl'])];
}
mysqli_free_result($res);

$expectedSynth = [
    [1, 'RJ45'],
    [2, 'RJ45'],
    [1, 'SFP'],
    [3, 'SFP+'],
];
itmdf_sort_test_assert_true(
    $got === $expectedSynth,
    'Synthetic rows strictly ordered RJ45-block then SFP-block by port_no'
);

$typeSqlFragment = 'COALESCE(spt.type, spt_any.type, \'RJ45\')';
$sqlDup =
    'SELECT pr.company_id, pr.position_id
     FROM idf_ports pr
     LEFT JOIN switch_port_types spt
       ON spt.id = pr.port_type AND spt.company_id = pr.company_id
     LEFT JOIN switch_port_types spt_any
       ON spt_any.id = pr.port_type
     GROUP BY pr.company_id, pr.position_id, pr.port_no
     HAVING COUNT(DISTINCT (
         CASE
             WHEN LOWER(REPLACE(REPLACE(TRIM(' . $typeSqlFragment . '), \' \', \'\'), \'+\', \'plus\')) LIKE \'sfp%\' THEN 1
             ELSE 0
         END
     )) = 2
     LIMIT 1';

$resDup = mysqli_query($db, $sqlDup);
$dupHost = ($resDup && ($rowDup = mysqli_fetch_assoc($resDup)));
if ($resDup) {
    mysqli_free_result($resDup);
}

if (!$dupHost) {
    echo "[INFO] No idf_ports row-set with RJ45 + SFP on same position_id/port_no — duplex monotonic proof skipped.\n";
} else {
    $cid = (int)$rowDup['company_id'];
    $pid = (int)$rowDup['position_id'];
    $fiberRankSel = '(' . trim(preg_replace("/\s+/", ' ', str_replace(["\r", "\n"], ' ', $rankSql))) . ')';
    $orderSqlDup = itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'ASC');
    $sqlLive =
        'SELECT pr.port_no AS n,'
        . ' ' . $typeSqlFragment . ' AS lbl,'
        . ' ' . $fiberRankSel . ' AS rk'
        . ' FROM idf_ports pr'
        . ' LEFT JOIN switch_port_types spt'
        . ' ON spt.id = pr.port_type AND spt.company_id = pr.company_id'
        . ' LEFT JOIN switch_port_types spt_any ON spt_any.id = pr.port_type'
        . ' WHERE pr.company_id = ? AND pr.position_id = ?'
        . ' ORDER BY ' . $orderSqlDup;

    $stmtLive = mysqli_prepare($db, $sqlLive);
    itmdf_sort_test_assert_true(
        $stmtLive !== false,
        'Prepared live duplex ORDER BY query (company_id=' . $cid . ' position_id=' . $pid . ')'
    );
    mysqli_stmt_bind_param($stmtLive, 'ii', $cid, $pid);
    mysqli_stmt_execute($stmtLive);
    $resLive = mysqli_stmt_get_result($stmtLive);
    itmdf_sort_test_assert_true($resLive !== false, 'Fetched rows for duplex ORDER BY proof');
    $rowCount = mysqli_num_rows($resLive);
    itmdf_sort_test_assert_true(
        $rowCount >= 2,
        'Duplex ORDER BY returns at least two rows (company_id=' . $cid . ' position_id=' . $pid . ')'
    );

    $priorRk = -1;
    $priorPort = -1;
    $firstLabel = '';
    while ($line = mysqli_fetch_assoc($resLive)) {
        $rk = (int)$line['rk'];
        $n = (int)$line['n'];
        $lbl = (string)$line['lbl'];
        if ($priorRk < 0) {
            $firstLabel = $lbl;
        }
        if ($rk < $priorRk) {
            itmdf_sort_test_fail(
                'Live duplex ORDER BY violates fiber_rank monotonicity (company_id=' . $cid . ' position_id=' . $pid . ').'
            );
        }
        if ($rk === $priorRk && $priorPort >= 0 && $n < $priorPort) {
            itmdf_sort_test_fail(
                'Live duplex ORDER BY violates port_no order within identical fiber_rank'
                . ' (company_id=' . $cid . ' position_id=' . $pid . ').'
            );
        }
        $priorRk = $rk;
        $priorPort = $n;
    }
    mysqli_free_result($resLive);
    mysqli_stmt_close($stmtLive);
    itmdf_sort_test_assert_true(
        $priorRk >= 1,
        'Live duplex sample reaches fiber rank after copper rows (company_id=' . $cid . ' position_id=' . $pid . ')'
    );
    itmdf_sort_test_pass(
        'Live duplex position ordered copper block before fiber'
        . ' (first type=' . ($firstLabel !== '' ? $firstLabel : '?') . ', rows=' . (string)$rowCount . ')'
    );
}

mysqli_close($db);

echo '[DONE] wired ORDER BY helpers + synthetic MySQL ORDER proof passed'
    . ($dupHost ? ' + duplex monotonic ORDER proof.' : '.') . PHP_EOL;

exit(0);
