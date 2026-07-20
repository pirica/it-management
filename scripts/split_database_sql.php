<?php
/**
 * Generate database/01_schema.sql, 02_triggers.sql, 03_data.sql from database.sql.
 *
 * Browser: scripts/split_database_sql.php (Administrator).
 * CLI: php scripts/split_database_sql.php [--apply]
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_database_sql_split.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Split database.sql');
$nl = itm_script_output_nl();

$root = dirname(__DIR__);
$monolith = $root . DIRECTORY_SEPARATOR . 'database.sql';
$apply = in_array('--apply', $argv ?? [], true)
    || (PHP_SAPI !== 'cli' && isset($_GET['apply']) && (string) $_GET['apply'] === '1');

if (!is_file($monolith)) {
    echo colorText('FAIL: database.sql not found.', 'fail') . $nl;
    exit(1);
}

$sql = file_get_contents($monolith);
if ($sql === false) {
    echo colorText('FAIL: cannot read database.sql.', 'fail') . $nl;
    exit(1);
}

$metrics = itm_database_sql_split_count_metrics($sql);
echo 'Monolith metrics: ' . $metrics['tables'] . ' tables, ' . $metrics['triggers'] . ' triggers, ' . $metrics['inserts'] . ' INSERT lines.' . $nl;

try {
    $buckets = itm_database_sql_split_monolith($sql);
} catch (Throwable $e) {
    echo colorText('FAIL: ' . $e->getMessage(), 'fail') . $nl;
    exit(1);
}

echo 'Parsed buckets: schema=' . count($buckets['schema'])
    . ', data=' . count($buckets['data'])
    . ', triggers=' . count($buckets['triggers'])
    . ', footer=' . count($buckets['footer'])
    . ', skipped=' . count($buckets['skipped']) . $nl;

if ($buckets['skipped'] !== []) {
    foreach ($buckets['skipped'] as $idx => $skipped) {
        $preview = substr(preg_replace('/\s+/', ' ', $skipped) ?? '', 0, 160);
        echo colorText('FAIL: unclassified statement #' . ($idx + 1) . ': ' . $preview, 'fail') . $nl;
    }
    exit(1);
}

if (!$apply) {
    echo colorText('Dry run only. Re-run with --apply or ?apply=1 to write database/01_schema.sql, 02_triggers.sql, 03_data.sql.', 'info') . $nl;
    exit(0);
}

try {
    $written = itm_database_sql_split_write_files($root, $monolith);
} catch (Throwable $e) {
    echo colorText('FAIL: ' . $e->getMessage(), 'fail') . $nl;
    exit(1);
}

foreach (['schema', 'data', 'triggers'] as $key) {
    $path = $written[$key];
    $body = file_get_contents($path);
    if ($body === false) {
        echo colorText('FAIL: cannot read written file ' . $path, 'fail') . $nl;
        exit(1);
    }
    $fileMetrics = itm_database_sql_split_count_metrics($body);
    echo 'Wrote ' . $path . ' (' . $fileMetrics['tables'] . ' tables, ' . $fileMetrics['triggers'] . ' triggers, ' . $fileMetrics['inserts'] . ' inserts).' . $nl;
}

$schemaBody = file_get_contents($written['schema']);
if ($schemaBody !== false) {
    $violations = itm_database_sql_split_schema_violations($schemaBody);
    foreach ($violations as $violation) {
        echo colorText('FAIL: ' . $violation, 'fail') . $nl;
        exit(1);
    }
}

echo colorText('OK: split files written. Run php scripts/verify_database_split_parity.php', 'pass') . $nl;
exit(0);
