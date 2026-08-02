<?php
/**
 * Live DB review: row counts, max IDs, and column types for BIGINT migration candidates.
 *
 * Browser + CLI (Admin). Pair with db/migrations/audit_logs_bigint.sql.
 *
 * CLI: php scripts/verify_bigint_table_review.php
 * Browser: scripts/verify_bigint_table_review.php?run=1
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_bigint_table_review.php</code> or <a href="verify_bigint_table_review.php?run=1">verify_bigint_table_review.php?run=1</a> (Administrator).
<p>Reports row counts, max <code>id</code>/<code>record_id</code>/<code>module_id</code>, column types, and AUTO_INCREMENT for BIGINT migration review tables. Pair with <code>db/migrations/audit_logs_bigint.sql</code>.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$tables = [
    'audit_logs',
    'modules_registry',
    'company_module_access',
    'company_module_share',
    'employee_sidebar_preferences',
    'system_access',
];

$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

if (!$conn instanceof mysqli) {
    itm_script_output_begin('BIGINT table review');
    echo colorText('[FAIL] Database connection failed.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$schema = DB_NAME;
$failures = 0;
$tableRows = [];
$columnLines = [];
$autoIncrementLines = [];
$guidanceLines = [
    'audit_logs: recommend BIGINT for id + record_id only (append-only, generic record pointer).',
    'modules_registry + company_module_access/share module_id: only if registry hub id widens (low volume today).',
    'employee_sidebar_preferences, system_access: INT sufficient at current scale.',
    'INT max signed: 2147483647',
];

foreach ($tables as $table) {
    $res = itm_run_query($conn, 'SELECT COUNT(*) AS c, MAX(`id`) AS max_id FROM `' . $conn->real_escape_string($table) . '`');
    if ($res === false) {
        $tableRows[] = [
            'table' => $table,
            'ok' => false,
            'detail' => 'count query failed',
        ];
        $failures++;
        continue;
    }
    $row = $res->fetch_assoc();
    $detail = 'rows=' . (int) ($row['c'] ?? 0) . ', max_id=' . ($row['max_id'] ?? 'NULL');

    if ($table === 'audit_logs') {
        $res2 = itm_run_query($conn, 'SELECT MAX(`record_id`) AS max_record_id FROM `audit_logs`');
        if ($res2 !== false) {
            $row2 = $res2->fetch_assoc();
            $detail .= ', max_record_id=' . ($row2['max_record_id'] ?? 'NULL');
        }
    }
    if ($table === 'company_module_access' || $table === 'company_module_share') {
        $res2 = itm_run_query($conn, 'SELECT MAX(`module_id`) AS max_module_id FROM `' . $conn->real_escape_string($table) . '`');
        if ($res2 !== false) {
            $row2 = $res2->fetch_assoc();
            $detail .= ', max_module_id=' . ($row2['max_module_id'] ?? 'NULL');
        }
    }

    $tableRows[] = [
        'table' => $table,
        'ok' => true,
        'detail' => $detail,
    ];
}

$inList = "'" . implode("','", array_map(static function ($t) use ($conn) {
    return $conn->real_escape_string($t);
}, $tables)) . "'";

$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'
          AND TABLE_NAME IN ({$inList})
          AND COLUMN_NAME IN ('id', 'record_id', 'module_id')
        ORDER BY TABLE_NAME, ORDINAL_POSITION";

$res = itm_run_query($conn, $sql);
if ($res === false) {
    $failures++;
    $columnLines[] = '[FAIL] information_schema column probe failed';
} else {
    while ($row = $res->fetch_assoc()) {
        $line = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'] . ' => ' . $row['COLUMN_TYPE'];
        if (!empty($row['EXTRA'])) {
            $line .= ' (' . $row['EXTRA'] . ')';
        }
        $columnLines[] = $line;
    }
}

$sqlAi = "SELECT TABLE_NAME, AUTO_INCREMENT
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'
            AND TABLE_NAME IN ({$inList})
          ORDER BY TABLE_NAME";

$resAi = itm_run_query($conn, $sqlAi);
if ($resAi === false) {
    $failures++;
    $autoIncrementLines[] = '[FAIL] information_schema auto_increment probe failed';
} else {
    while ($row = $resAi->fetch_assoc()) {
        $autoIncrementLines[] = $row['TABLE_NAME'] . ': AUTO_INCREMENT=' . ($row['AUTO_INCREMENT'] ?? 'NULL');
    }
}

itm_script_output_begin('BIGINT table review');
$nl = itm_script_output_nl();

if (!$isCli) {
    itm_script_output_close_pre();
    echo '<h1>BIGINT migration review</h1>';
    echo '<p>Database: <code>' . htmlspecialchars($schema, ENT_QUOTES, 'UTF-8') . '</code>. ';
    echo 'Live row counts and column types for tables under BIGINT review.</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:12px 0;">';
    echo '<thead><tr><th>Table</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
    foreach ($tableRows as $row) {
        $ok = !empty($row['ok']);
        $color = $ok ? '#1a7f37' : '#cf222e';
        $label = $ok ? 'OK' : 'FAIL';
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars((string) $row['table'], ENT_QUOTES, 'UTF-8') . '</code></td>';
        echo '<td style="color:' . $color . ';font-weight:600;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['detail'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo colorText('BIGINT migration table review', 'info') . $nl;
echo '[INFO] Database: ' . $schema . $nl;
echo '[INFO] Tables: ' . count($tables) . ' | Failures: ' . $failures . $nl;
echo str_repeat('-', 72) . $nl;

echo colorText('=== ROW COUNTS / MAX IDs ===', 'info') . $nl;
foreach ($tableRows as $row) {
    $ok = !empty($row['ok']);
    $line = ($ok ? '[PASS] ' : '[FAIL] ') . $row['table'] . ' — ' . $row['detail'];
    echo colorText($line, $ok ? 'pass' : 'fail') . $nl;
}

echo $nl . colorText('=== COLUMN TYPES (id / record_id / module_id) ===', 'info') . $nl;
foreach ($columnLines as $line) {
    $type = strpos($line, '[FAIL]') === 0 ? 'fail' : 'info';
    echo colorText($line, $type) . $nl;
}

echo $nl . colorText('=== AUTO_INCREMENT ===', 'info') . $nl;
foreach ($autoIncrementLines as $line) {
    $type = strpos($line, '[FAIL]') === 0 ? 'fail' : 'info';
    echo colorText($line, $type) . $nl;
}

echo $nl . colorText('=== BIGINT MIGRATION GUIDANCE ===', 'info') . $nl;
foreach ($guidanceLines as $line) {
    echo colorText('- ' . $line, 'info') . $nl;
}

echo str_repeat('-', 72) . $nl;
if ($failures === 0) {
    echo colorText('[PASS] BIGINT review checks completed for this database.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] ' . $failures . ' BIGINT review check(s) failed.', 'fail') . $nl;
itm_script_output_end();
exit(1);
