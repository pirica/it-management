<?php
/**
 * Table Repair Helper (CLI)
 *
 * Why: Some environments can hit InnoDB metadata drift where a table appears
 * in phpMyAdmin but returns "doesn't exist in engine" during ANALYZE TABLE.
 * This helper rebuilds one table from database.sql safely by explicit table name.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from CLI.\n");
}

$options = getopt('', ['table:']);
$tableName = isset($options['table']) ? trim((string) $options['table']) : '';

if ($tableName === '') {
    fwrite(STDERR, "Usage: php scripts/repair_table_from_schema.php --table=<table_name>\n");
    exit(1);
}

try {
    require_once dirname(__DIR__) . '/config/config.php';
} catch (Throwable $e) {
    fwrite(STDERR, "Unable to bootstrap application config/db connection: " . $e->getMessage() . "\n");
    exit(1);
}

if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

if (!itm_is_safe_identifier($tableName)) {
    fwrite(STDERR, "Invalid table name.\n");
    exit(1);
}

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    fwrite(STDERR, "database.sql not found at expected path.\n");
    exit(1);
}

$schemaSql = file_get_contents($sqlPath);
if ($schemaSql === false || $schemaSql === '') {
    fwrite(STDERR, "Unable to read database.sql content.\n");
    exit(1);
}

$createPattern = '/CREATE TABLE\s+`' . preg_quote($tableName, '/') . '`\s*\(.*?\)\s*ENGINE=.*?;/si';
if (!preg_match($createPattern, $schemaSql, $matches)) {
    fwrite(STDERR, "Could not find CREATE TABLE statement for '{$tableName}' in database.sql.\n");
    exit(1);
}

$createSql = trim((string) $matches[0]);

fwrite(STDOUT, "Rebuilding table '{$tableName}' from database.sql...\n");

if (!itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 0')) {
    fwrite(STDERR, "Failed to disable FOREIGN_KEY_CHECKS.\n");
    exit(1);
}

$dropSql = "DROP TABLE IF EXISTS `{$tableName}`";
if (!itm_run_query($conn, $dropSql)) {
    itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, "Failed to drop table '{$tableName}'.\n");
    exit(1);
}

if (!itm_run_query($conn, $createSql)) {
    itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, "Failed to recreate table '{$tableName}' from schema definition.\n");
    exit(1);
}

if (!itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1')) {
    fwrite(STDERR, "Warning: failed to re-enable FOREIGN_KEY_CHECKS. Please verify manually.\n");
    exit(1);
}

fwrite(STDOUT, "Table '{$tableName}' rebuilt successfully.\n");
fwrite(STDOUT, "Next step: run php scripts/analyze_database_health.php to verify.\n");
exit(0);
