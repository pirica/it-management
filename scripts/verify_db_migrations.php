<?php
/**
 * Compare live DB state against db/migrations/*.sql expectations (no migration history table).
 *
 * Browser + CLI (Admin). Reports Applied / Superseded / Not applied per migration file.
 *
 * CLI: php scripts/verify_db_migrations.php
 * CLI: php scripts/verify_db_migrations.php --json
 * Browser: scripts/verify_db_migrations.php
 */

declare(strict_types=1);

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_verify_db_migrations_report.php';

$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
$asJson = $isCli
    ? in_array('--json', $GLOBALS['argv'] ?? [], true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

if (!$conn instanceof mysqli) {
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Database connection failed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        itm_script_output_begin('Verify db/migrations');
        echo colorText('[FAIL] Database connection failed.', 'fail') . itm_script_output_nl();
        itm_script_output_end();
    }
    exit(1);
}

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$report = itm_verify_db_migrations_report($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit($report['ok'] ? 0 : 1);
}

itm_script_output_begin('Verify db/migrations');
$nl = itm_script_output_nl();

if (!$isCli) {
    itm_script_output_close_pre();
    echo '<h1>db/migrations vs live database</h1>';
    echo '<p>Database: <code>' . htmlspecialchars((string)$report['database'], ENT_QUOTES, 'UTF-8') . '</code>. ';
    echo 'Probes schema/data only — there is no applied-migration log table.</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:12px 0;">';
    echo '<thead><tr><th>Migration file</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
    foreach ($report['migrations'] as $row) {
        $status = (string)$row['status'];
        $color = $status === 'fail' ? '#cf222e' : ($status === 'superseded' ? '#57606a' : '#1a7f37');
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars((string)$row['file'], ENT_QUOTES, 'UTF-8') . '</code></td>';
        echo '<td style="color:' . $color . ';font-weight:600;">' . htmlspecialchars((string)$row['label'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['detail'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo colorText('db/migrations live DB verification', 'info') . $nl;
echo '[INFO] Database: ' . (string)$report['database'] . $nl;
echo '[INFO] Pass: ' . (int)$report['summary']['pass']
    . ' | Superseded: ' . (int)$report['summary']['superseded']
    . ' | Fail: ' . (int)$report['summary']['fail'] . $nl;
echo str_repeat('-', 72) . $nl;

foreach ($report['migrations'] as $row) {
    $prefix = '[PASS]';
    $type = 'pass';
    if ($row['status'] === 'fail') {
        $prefix = '[FAIL]';
        $type = 'fail';
    } elseif ($row['status'] === 'superseded') {
        $prefix = '[INFO]';
        $type = 'info';
    }
    $line = $prefix . ' ' . $row['file'] . ' — ' . $row['label'] . ': ' . $row['detail'];
    echo colorText($line, $type) . $nl;
}

echo str_repeat('-', 72) . $nl;
if ($report['ok']) {
    echo colorText('[PASS] All db/migrations checks passed for this database.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] ' . (int)$report['failures'] . ' migration check(s) failed.', 'fail') . $nl;
itm_script_output_end();
exit(1);
