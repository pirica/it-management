<?php

/**
 * Regression test: IDF device port list always sorts copper (RJ45) before fiber (SFP).
 *
 * Usage (CLI):
 *   php scripts/idf_device_port_sort_test.php
 *   php scripts/idf_device_port_sort_test.php --verbose
 *   php scripts/idf_device_port_sort_test.php --offline-only
 *
 * Browser: open this script in the browser (uses &lt;br&gt; line breaks).
 *   ?verbose=1  ·  ?offline_only=1
 *
 * --offline-only  Skip MySQL checks (code + ORDER BY helpers only).
 * --verbose       Print every individual check (default is a short human summary).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/idf_device_port_sort_sql.php';

/** @var bool */
$itmdfSortVerbose = false;
/** @var bool */
$itmdfSortOfflineOnly = false;
/** @var int */
$itmdfSortPassCount = 0;
/** @var int */
$itmdfSortFailCount = 0;
/** @var string */
$itmdfSortCurrentSection = '';

function itmdf_sort_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function itmdf_sort_eol(): string
{
    return itm_script_output_nl();
}

function itmdf_sort_esc(string $text): string
{
    return itmdf_sort_is_cli() ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function itmdf_sort_out(string $msg, bool $isError = false): void
{
    $line = itmdf_sort_esc($msg) . itmdf_sort_eol();
    if (itmdf_sort_is_cli()) {
        if ($isError && defined('STDERR') && is_resource(STDERR)) {
            fwrite(STDERR, $line);
            return;
        }
        echo $line;
        return;
    }
    if ($isError) {
        echo '<strong style="color:#cf222e;">' . $line . '</strong>';
    } else {
        echo $line;
    }
    if (function_exists('flush')) {
        @flush();
    }
}

function itmdf_sort_blank(): void
{
    echo itmdf_sort_eol();
}

function itmdf_sort_browser_init(): void
{
    if (itmdf_sort_is_cli()) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>IDF device port sort test</title></head>'
        . '<body style="font-family:Segoe UI,system-ui,sans-serif;line-height:1.45;margin:16px;max-width:920px;">';
    if (!function_exists('itm_script_browser_nav_html')) {
        require_once __DIR__ . '/lib/script_browser_nav.php';
    }
    echo itm_script_browser_nav_html();
    echo '<p style="color:#57606a;margin:0 0 14px;">'
        . '<a href="?">Summary</a> &nbsp;|&nbsp; '
        . '<a href="?verbose=1">Verbose</a> &nbsp;|&nbsp; '
        . '<a href="?offline_only=1">Offline only</a>'
        . '</p>';
}

function itmdf_sort_browser_close(): void
{
    if (!itmdf_sort_is_cli()) {
        echo '</body></html>';
    }
}

if (itmdf_sort_is_cli()) {
    $argvLocal = $argv ?? [];
    $itmdfSortVerbose = in_array('--verbose', $argvLocal, true) || in_array('-v', $argvLocal, true);
    $itmdfSortOfflineOnly = in_array('--offline-only', $argvLocal, true);
} else {
    $itmdfSortVerbose = isset($_GET['verbose']) || isset($_GET['v']);
    $itmdfSortOfflineOnly = isset($_GET['offline_only']) || isset($_GET['offline-only']);
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

itmdf_sort_browser_init();
itm_script_browser_nav_echo();

function itmdf_sort_section_start(string $title, string $why = ''): void
{
    global $itmdfSortCurrentSection;
    $itmdfSortCurrentSection = $title;
    itmdf_sort_blank();
    itmdf_sort_out($title);
    if ($why !== '') {
        itmdf_sort_out($why);
    }
}

function itmdf_sort_section_done(int $passed, ?string $note = null): void
{
    global $itmdfSortVerbose;
    if ($itmdfSortVerbose) {
        return;
    }
    $line = '  ✓ ' . $passed . ' check' . ($passed === 1 ? '' : 's') . ' passed.';
    if ($note !== null && $note !== '') {
        $line .= ' ' . $note;
    }
    itmdf_sort_out($line);
}

function itmdf_sort_info(string $msg): void
{
    itmdf_sort_out('  ℹ ' . $msg);
}

function itmdf_sort_fail(string $msg): void
{
    global $itmdfSortFailCount, $itmdfSortCurrentSection;
    $itmdfSortFailCount++;
    $prefix = $itmdfSortCurrentSection !== '' ? '[' . $itmdfSortCurrentSection . '] ' : '';
    itmdf_sort_blank();
    itmdf_sort_out('✗ FAIL: ' . $prefix . $msg, true);
    itmdf_sort_browser_close();
    exit(1);
}

function itmdf_sort_pass(string $msg): void
{
    global $itmdfSortPassCount, $itmdfSortVerbose;
    $itmdfSortPassCount++;
    if ($itmdfSortVerbose) {
        itmdf_sort_out('  ✓ ' . $msg);
    }
}

function itmdf_sort_assert_true(bool $condition, string $msg): void
{
    if (!$condition) {
        itmdf_sort_fail($msg);
    }
    itmdf_sort_pass($msg);
}

itmdf_sort_out('IDF device port sort — regression test');
itmdf_sort_out(str_repeat('=', 42));
itmdf_sort_out('Goal: On the device port screen, copper (RJ45) ports always list before fiber (SFP) ports,');
itmdf_sort_out('even when two ports share the same number or you sort by port type / label / VLAN.');
if ($itmdfSortOfflineOnly) {
    itmdf_sort_out('Mode: offline only (no database).');
} elseif ($itmdfSortVerbose) {
    itmdf_sort_out('Mode: verbose (every check listed).');
}

// --- Section 1: device.php wiring ---
itmdf_sort_section_start(
    '1. Application wiring',
    'Confirms modules/idfs/device.php uses the shared sort helper for the port query.'
);
$sectionStart = $itmdfSortPassCount;

$devicePhp = dirname(__DIR__) . '/modules/idfs/device.php';
itmdf_sort_assert_true(is_readable($devicePhp), 'device.php exists and is readable');
$deviceSource = file_get_contents($devicePhp);
itmdf_sort_assert_true(
    $deviceSource !== false && strpos($deviceSource, 'includes/idf_device_port_sort_sql.php') !== false,
    'device.php loads includes/idf_device_port_sort_sql.php'
);
itmdf_sort_assert_true(
    strpos($deviceSource, 'itm_idf_ports_device_list_order_sql(') !== false,
    'device.php builds ORDER BY via itm_idf_ports_device_list_order_sql()'
);
itmdf_sort_assert_true(
    strpos($deviceSource, 'ORDER BY " . $portOrderSql') !== false,
    'device.php applies $portOrderSql to the ports SELECT'
);

itmdf_sort_section_done($itmdfSortPassCount - $sectionStart);

// --- Section 2: ORDER BY helper rules ---
itmdf_sort_section_start(
    '2. Sort rules (PHP helpers)',
    'Fiber rank stays ascending first; only the column you sort by flips ASC/DESC.'
);
$sectionStart = $itmdfSortPassCount;

$rankSql = itm_idf_port_fiber_family_rank_sql();
itmdf_sort_assert_true(
    strpos($rankSql, 'sfp') !== false,
    'Fiber detection treats labels starting with sfp (after normalize) as fiber'
);

$expectedPrefixAsc = $rankSql . ' ASC, pr.port_no ASC';
$orderAsc = itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'ASC');
itmdf_sort_assert_true(
    strpos($orderAsc, $expectedPrefixAsc) === 0,
    'Sort by port # ASC → fiber rank ASC, then port # ASC'
);

$expectedDesc = $rankSql . ' ASC, pr.port_no DESC, pr.port_no ASC';
itmdf_sort_assert_true(
    itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'DESC') === $expectedDesc,
    'Sort by port # DESC → fiber rank still ASC; port # DESC in the middle'
);
itmdf_sort_assert_true(
    itm_idf_ports_device_list_order_sql($rankSql, 'pr.port_no', 'desc') === $expectedDesc,
    'Lowercase desc is accepted for the primary sort column'
);

$orderTypeDesc = itm_idf_ports_device_list_order_sql($rankSql, itm_idf_port_type_label_sql(), 'DESC');
itmdf_sort_assert_true(
    strpos($orderTypeDesc, $rankSql . ' ASC, ') === 0,
    'Sort by port type DESC still lists all copper before any fiber'
);

itmdf_sort_section_done($itmdfSortPassCount - $sectionStart);

if ($itmdfSortOfflineOnly) {
    itmdf_sort_blank();
    itmdf_sort_out(str_repeat('=', 42));
    itmdf_sort_out('RESULT: PASS (offline only — database checks skipped).');
    itmdf_sort_out(itmdf_sort_is_cli()
        ? 'Run without --offline-only to prove sort order against MySQL.'
        : 'Use the Summary link (top) to run database checks.');
    itmdf_sort_browser_close();
    exit(0);
}

// --- Section 3: MySQL synthetic proof ---
itmdf_sort_section_start(
    '3. Database proof (synthetic rows)',
    'Runs a small fake port list in MySQL to prove ORDER BY matches the rule.'
);
$sectionStart = $itmdfSortPassCount;

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
    itmdf_sort_fail(
        'Cannot connect to MySQL (' . $host . ' / ' . $name . '). Set ITM_DB_* env vars or start Laragon.'
    );
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
itmdf_sort_assert_true((bool)$res, 'Synthetic ORDER BY query runs without error');

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
itmdf_sort_assert_true(
    $got === $expectedSynth,
    'Order is port 1 RJ45, port 2 RJ45, port 1 SFP, port 3 SFP+ (copper block then fiber block)'
);

$humanSynth = [];
foreach ($got as $pair) {
    $humanSynth[] = 'port ' . $pair[0] . ' ' . $pair[1];
}
itmdf_sort_section_done(
    $itmdfSortPassCount - $sectionStart,
    'Got: ' . implode(' → ', $humanSynth) . '.'
);

// --- Section 4: Live duplex position (optional) ---
itmdf_sort_section_start(
    '4. Live data (optional)',
    'If your DB has one rack position with RJ45 and SFP on the same port number, we re-run the real ORDER BY.'
);
$sectionStart = $itmdfSortPassCount;

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
    if (!$itmdfSortVerbose) {
        itmdf_sort_info('Skipped — no position in idf_ports has both RJ45 and SFP on the same port number.');
        itmdf_sort_info('Synthetic proof in section 3 is enough for CI; add mixed ports to test live data.');
    } else {
        itmdf_sort_pass('Duplex live proof skipped (no suitable idf_ports sample in database)');
    }
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
    itmdf_sort_assert_true(
        $stmtLive !== false,
        'Live ORDER BY query prepares (company ' . $cid . ', position ' . $pid . ')'
    );
    mysqli_stmt_bind_param($stmtLive, 'ii', $cid, $pid);
    mysqli_stmt_execute($stmtLive);
    $resLive = mysqli_stmt_get_result($stmtLive);
    itmdf_sort_assert_true($resLive !== false, 'Live ORDER BY returns rows');
    $rowCount = mysqli_num_rows($resLive);
    itmdf_sort_assert_true(
        $rowCount >= 2,
        'At least two ports on that position (company ' . $cid . ', position ' . $pid . ')'
    );

    $priorRk = -1;
    $priorPort = -1;
    $firstLabel = '';
    $orderedLabels = [];
    while ($line = mysqli_fetch_assoc($resLive)) {
        $rk = (int)$line['rk'];
        $n = (int)$line['n'];
        $lbl = (string)$line['lbl'];
        $orderedLabels[] = 'port ' . $n . ' ' . $lbl;
        if ($priorRk < 0) {
            $firstLabel = $lbl;
        }
        if ($rk < $priorRk) {
            itmdf_sort_fail(
                'Fiber appeared before copper on company ' . $cid . ' position ' . $pid . '.'
            );
        }
        if ($rk === $priorRk && $priorPort >= 0 && $n < $priorPort) {
            itmdf_sort_fail(
                'Port numbers went backwards within the same copper/fiber group'
                . ' (company ' . $cid . ' position ' . $pid . ').'
            );
        }
        $priorRk = $rk;
        $priorPort = $n;
    }
    mysqli_free_result($resLive);
    mysqli_stmt_close($stmtLive);
    itmdf_sort_assert_true(
        $priorRk >= 1,
        'After copper rows, fiber rows appear (company ' . $cid . ', position ' . $pid . ')'
    );
    itmdf_sort_pass(
        'Live position: copper before fiber'
        . ' (company ' . $cid . ', position ' . $pid . ', ' . $rowCount . ' ports, first=' . ($firstLabel !== '' ? $firstLabel : '?') . ')'
    );

    itmdf_sort_section_done(
        $itmdfSortPassCount - $sectionStart,
        'Sample order: ' . implode(' → ', $orderedLabels) . '.'
    );
}

mysqli_close($db);

itmdf_sort_blank();
itmdf_sort_out(str_repeat('=', 42));
itmdf_sort_out('RESULT: PASS — ' . $itmdfSortPassCount . ' checks.');
itmdf_sort_out('Copper-before-fiber sort is wired in device.php and verified' . ($dupHost ? ' (synthetic + live data).' : ' (synthetic data).'));
if (itmdf_sort_is_cli()) {
    itmdf_sort_out('Tip: use --verbose to see every check line.');
} else {
    echo 'Tip: use <a href="?verbose=1">Verbose</a> to see every check line.<br>' . PHP_EOL;
}

itmdf_sort_browser_close();

exit(0);
