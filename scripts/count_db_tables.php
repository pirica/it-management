<?php
/**
 * Why: External monitors and deploy checks need a single table count without signing in.
 * Counts live tables in information_schema for DB_NAME and mirrors the value to number_db_tables.txt.
 *
 * Browser: open scripts/count_db_tables.php (no login).
 * CLI: php scripts/count_db_tables.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Open <a href="count_db_tables.php">count_db_tables.php</a> (plain number response) or run <code>php scripts/count_db_tables.php</code> from the repository root. Output file: <code>scripts/number_db_tables.txt</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

// Why: config.php allowlists this script for browser access without a session.
if (!defined('ITM_SCRIPT_NO_AUTH')) {
    define('ITM_SCRIPT_NO_AUTH', true);
}

require_once dirname(__DIR__) . '/config/config.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/itm_script_browser_usage.php';
    itm_script_browser_usage_maybe_gate([]);
}

$schema = DB_NAME;
$count = 0;
$dbErrorCode = null;
$dbErrorMessage = null;
$res = itm_run_query(
    $conn,
    "SELECT COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($schema) . "'",
    $dbErrorCode,
    $dbErrorMessage
);

if ($res === false) {
    $message = 'Failed to count tables in database ' . $schema . '.';
    if ($dbErrorMessage !== null && $dbErrorMessage !== '') {
        $message .= ' MySQL error (' . $dbErrorCode . '): ' . $dbErrorMessage;
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $message;
    }
    exit(1);
}

$row = $res->fetch_assoc();
if (is_array($row) && isset($row['table_count'])) {
    $count = (int) $row['table_count'];
}

$outputPath = __DIR__ . '/number_db_tables.txt';
$outputBody = (string) $count . "\n";
if (file_put_contents($outputPath, $outputBody) === false) {
    $message = 'Failed to write ' . $outputPath;
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $message;
    }
    exit(1);
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo (string) $count;
if (PHP_SAPI === 'cli') {
    echo "\n";
}

exit(0);
