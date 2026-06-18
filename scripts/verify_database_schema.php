<?php
/**
 * Why: Deploy scripts can report "success" while MySQL stopped early (e.g. 73/116 tables).
 * Compare tables defined in database.sql with information_schema for itmanagement.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CLI only</title></head><body>';
    echo '<p>Run from repository root:</p><pre>php scripts/verify_database_schema.php</pre>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit;
}

// Why: config.php skips web auth redirects only when ITM_CLI_SCRIPT is set before load.
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';

/**
 * @return list<string>
 */
function itm_verify_expected_tables_from_database_sql(): array
{
    $path = ROOT_PATH . 'database.sql';
    if (!is_readable($path)) {
        fwrite(STDERR, "Cannot read database.sql at {$path}\n");
        exit(2);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read database.sql\n");
        exit(2);
    }
    if (!preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches)) {
        fwrite(STDERR, "No CREATE TABLE entries found in database.sql\n");
        exit(2);
    }
    return $matches[1];
}

$expected = itm_verify_expected_tables_from_database_sql();
sort($expected, SORT_STRING);

$schema = DB_NAME;
$actual = [];
$dbErrorCode = null;
$dbErrorMessage = null;
$res = itm_run_query(
    $conn,
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "' ORDER BY TABLE_NAME",
    $dbErrorCode,
    $dbErrorMessage
);
if ($res === false) {
    fwrite(STDERR, "Failed to read information_schema for database '{$schema}'.\n");
    if ($dbErrorMessage !== null && $dbErrorMessage !== '') {
        fwrite(STDERR, "MySQL error ({$dbErrorCode}): {$dbErrorMessage}\n");
    }
    exit(2);
}
while ($row = $res->fetch_assoc()) {
    $actual[] = (string) $row['TABLE_NAME'];
}

$missing = array_values(array_diff($expected, $actual));
$extra = array_values(array_diff($actual, $expected));

echo "Database: {$schema}\n";
echo 'Expected tables (database.sql): ' . count($expected) . "\n";
echo 'Actual tables (MySQL): ' . count($actual) . "\n";

if ($missing !== []) {
    echo "\nMissing tables (" . count($missing) . "):\n";
    foreach ($missing as $name) {
        echo "  - {$name}\n";
    }
}

if ($extra !== []) {
    echo "\nExtra tables not in database.sql (" . count($extra) . "):\n";
    foreach ($extra as $name) {
        echo "  - {$name}\n";
    }
}

if ($missing === [] && $extra === [] && count($actual) === count($expected)) {
    echo "\nOK — schema matches database.sql.\n";
    exit(0);
}

echo "\nFAIL — import incomplete or stale schema. Re-import the full database.sql (include DROP DATABASE at top; password itmanagement).\n";
echo "Check mysql stderr (e.g. mysql-import.err) for the first ERROR after the last table created.\n";
exit(1);
