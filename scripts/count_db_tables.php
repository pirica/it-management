<?php
/**
 * Why: External monitors and deploy checks need a single table count without signing in.
 * Counts live tables in information_schema for DB_NAME and prints the total (browser or CLI only).
 *
 * Browser: open scripts/count_db_tables.php (loopback or ITM_SCRIPT_NO_AUTH_ALLOWED_IPS; no employee login).
 * CLI: php scripts/count_db_tables.php
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    // Fix: ensure CLI uses the script directory as working directory
    chdir(__DIR__);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (!defined('ITM_SCRIPT_NO_AUTH')) {
    define('ITM_SCRIPT_NO_AUTH', true);
}

// Fix: absolute path, CLI-safe
require_once realpath(__DIR__ . '/../config/config.php');

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

    // Fix: PowerShell hides STDERR → send errors to STDOUT
    fwrite(STDOUT, $message . "\n");
    exit(1);
}

$row = $res->fetch_assoc();
if (is_array($row) && isset($row['table_count'])) {
    $count = (int) $row['table_count'];
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo (string) $count;
if (PHP_SAPI === 'cli') {
    echo "\n";
}

exit(0);
