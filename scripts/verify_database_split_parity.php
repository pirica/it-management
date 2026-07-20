<?php
/**
 * Verify db/ split files stay aligned with database.sql.
 *
 * Browser: scripts/verify_database_split_parity.php (Administrator).
 * CLI: php scripts/verify_database_split_parity.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_database_sql_split.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Verify database split parity');
$nl = itm_script_output_nl();

$root = dirname(__DIR__);
$monolith = $root . DIRECTORY_SEPARATOR . 'database.sql';
$paths = [
    'schema' => $root . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql',
    'triggers' => $root . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '02_triggers.sql',
    'data' => $root . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '03_data.sql',
];

$failures = [];

foreach (array_merge([$monolith], array_values($paths)) as $path) {
    if (!is_file($path)) {
        $failures[] = 'Missing file: ' . $path;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo colorText('FAIL: ' . $failure, 'fail') . $nl;
    }
    exit(1);
}

$monoSql = file_get_contents($monolith);
if ($monoSql === false) {
    echo colorText('FAIL: cannot read database.sql', 'fail') . $nl;
    exit(1);
}

$monoBuckets = itm_database_sql_split_monolith($monoSql);
if ($monoBuckets['skipped'] !== []) {
    echo colorText('FAIL: database.sql has ' . count($monoBuckets['skipped']) . ' unclassified statements (re-run split_database_sql.php after fixing parser).', 'fail') . $nl;
    exit(1);
}

$monoMetrics = itm_database_sql_split_count_metrics($monoSql);
$schemaMetrics = itm_database_sql_split_count_metrics(file_get_contents($paths['schema']) ?: '');
$triggerMetrics = itm_database_sql_split_count_metrics(file_get_contents($paths['triggers']) ?: '');
$dataMetrics = itm_database_sql_split_count_metrics(file_get_contents($paths['data']) ?: '');

$expectedTables = $monoMetrics['tables'];
$expectedTriggers = $monoMetrics['triggers'];

echo 'Expected from database.sql: ' . $expectedTables . ' tables, ' . $expectedTriggers . ' triggers.' . $nl;
echo '01_schema.sql: ' . $schemaMetrics['tables'] . ' tables.' . $nl;
echo '02_triggers.sql: ' . $triggerMetrics['triggers'] . ' triggers.' . $nl;
echo '03_data.sql: ' . $dataMetrics['inserts'] . ' INSERT lines (monolith grep includes trigger bodies: ' . $monoMetrics['inserts'] . ').' . $nl;

if ($schemaMetrics['tables'] !== $expectedTables) {
    $failures[] = '01_schema.sql table count ' . $schemaMetrics['tables'] . ' != ' . $expectedTables;
}
if ($triggerMetrics['triggers'] !== $expectedTriggers) {
    $failures[] = '02_triggers.sql trigger count ' . $triggerMetrics['triggers'] . ' != ' . $expectedTriggers;
}
if ($schemaMetrics['triggers'] !== 0 || $schemaMetrics['inserts'] !== 0) {
    $failures[] = '01_schema.sql must not contain INSERT or CREATE TRIGGER statements';
}
if ($dataMetrics['tables'] !== 0 || $dataMetrics['triggers'] !== 0) {
    $failures[] = '03_data.sql must not contain CREATE TABLE or CREATE TRIGGER statements';
}

$schemaViolations = itm_database_sql_split_schema_violations(file_get_contents($paths['schema']) ?: '');
foreach ($schemaViolations as $violation) {
    $failures[] = $violation;
}

/**
 * @return list<string>
 */
$normalizeDataStatements = static function (array $statements): array {
    $normalized = [];
    foreach ($statements as $statement) {
        $line = preg_replace('/\s+/', ' ', trim($statement));
        if ($line !== null && $line !== '') {
            $normalized[] = $line;
        }
    }
    sort($normalized, SORT_STRING);

    return $normalized;
};

$monoData = $normalizeDataStatements($monoBuckets['data']);
$splitDataSql = file_get_contents($paths['data']);
$splitBuckets = itm_database_sql_split_monolith($splitDataSql === false ? '' : $splitDataSql);
$splitData = $normalizeDataStatements($splitBuckets['data']);

if ($monoData !== $splitData) {
    $failures[] = '03_data.sql DML does not match database.sql data bucket (count mono=' . count($monoData) . ', split=' . count($splitData) . ')';
}

preg_match_all('/^CREATE TABLE `([^`]+)`/m', $monoSql, $monoTables);
preg_match_all('/^CREATE TABLE `([^`]+)`/m', file_get_contents($paths['schema']) ?: '', $schemaTables);
sort($monoTables[1]);
sort($schemaTables[1]);
if ($monoTables[1] !== $schemaTables[1]) {
    $failures[] = 'CREATE TABLE name list mismatch between database.sql and 01_schema.sql';
}

preg_match_all('/^CREATE TRIGGER `([^`]+)`/m', $monoSql, $monoTriggers);
preg_match_all('/^CREATE TRIGGER `([^`]+)`/m', file_get_contents($paths['triggers']) ?: '', $splitTriggers);
sort($monoTriggers[1]);
sort($splitTriggers[1]);
if ($monoTriggers[1] !== $splitTriggers[1]) {
    $failures[] = 'CREATE TRIGGER name list mismatch between database.sql and 02_triggers.sql';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo colorText('FAIL: ' . $failure, 'fail') . $nl;
    }
    exit(1);
}

echo colorText('PASS: split files match database.sql (' . count($monoData) . ' data statements, ' . $expectedTables . ' tables, ' . $expectedTriggers . ' triggers).', 'pass') . $nl;
echo 'Import order (single MySQL session): db/01_schema.sql → db/03_data.sql → db/02_triggers.sql' . $nl;
echo 'Or: bash scripts/import_database_split.sh' . $nl;
exit(0);
