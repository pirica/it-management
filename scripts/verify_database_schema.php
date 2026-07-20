<?php
/**
 * Why: Deploy scripts can report "success" while MySQL stopped early (e.g. 73/130 tables).
 * Compare tables defined in database.sql with information_schema for itmanagement.
 */
declare(strict_types=1);

// Why: config.php skips web auth redirects only when ITM_CLI_SCRIPT is set before load.
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$conn instanceof mysqli) {
    $msg = "Database connection failed.";
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . "\n");
    } else {
        itm_script_output_begin('Database Schema Verification');
        echo colorText($msg, 'fail') . itm_script_output_nl();
    }
    exit(1);
}

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

itm_script_output_begin('Database Schema Verification');
$nl = itm_script_output_nl();

/**
 * @return list<string>
 */
function itm_verify_expected_tables_from_database_sql(): array
{
    global $nl;
    $path = ROOT_PATH . 'database.sql';
    if (!is_readable($path)) {
        $msg = "Cannot read database.sql at {$path}";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
        } else {
            echo colorText($msg, 'fail') . $nl;
        }
        exit(2);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        $msg = "Failed to read database.sql";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
        } else {
            echo colorText($msg, 'fail') . $nl;
        }
        exit(2);
    }
    if (!preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches)) {
        $msg = "No CREATE TABLE entries found in database.sql";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
        } else {
            echo colorText($msg, 'fail') . $nl;
        }
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
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($conn, $schema) . "' ORDER BY TABLE_NAME",
    $dbErrorCode,
    $dbErrorMessage
);
if ($res === false) {
    $msg = "Failed to read information_schema for database '{$schema}'.";
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . "\n");
        if ($dbErrorMessage !== null && $dbErrorMessage !== '') {
            fwrite(STDERR, "MySQL error ({$dbErrorCode}): {$dbErrorMessage}\n");
        }
    } else {
        echo colorText($msg, 'fail') . $nl;
        if ($dbErrorMessage !== null && $dbErrorMessage !== '') {
            echo "MySQL error ({$dbErrorCode}): {$dbErrorMessage}" . $nl;
        }
    }
    exit(2);
}
while ($row = $res->fetch_assoc()) {
    $actual[] = (string) $row['TABLE_NAME'];
}

$missing = array_values(array_diff($expected, $actual));
$extra = array_values(array_diff($actual, $expected));

echo "Database: {$schema}" . $nl;
echo "Expected tables (database.sql): " . count($expected) . $nl;
echo "Actual tables (MySQL): " . count($actual) . $nl;

if ($missing !== []) {
    echo $nl . "Missing tables (" . count($missing) . "):" . $nl;
    foreach ($missing as $name) {
        echo "  - {$name}" . $nl;
    }
}

if ($extra !== []) {
    echo $nl . "Extra tables not in database.sql (" . count($extra) . "):" . $nl;
    foreach ($extra as $name) {
        echo "  - {$name}" . $nl;
    }
}

if ($missing === [] && $extra === [] && count($actual) === count($expected)) {
    echo $nl . itm_script_format_status_line("[PASS] OK — schema matches database.sql.") . $nl;
    exit(0);
}

echo $nl . itm_script_format_status_line("[FAIL] FAIL — import incomplete or stale schema. Re-import the full database.sql (include DROP DATABASE at top; password itmanagement) or run bash scripts/import_database_split.sh after regenerating db/*.sql.") . $nl;
echo "Check mysql stderr (e.g. mysql-import.err) for the first ERROR after the last table created." . $nl;
exit(1);

itm_script_output_end();
