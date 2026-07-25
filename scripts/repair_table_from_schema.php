<?php
/**
 * Table Repair Helper
 *
 * Why: Some environments can hit InnoDB metadata drift where a table appears
 * in phpMyAdmin but returns "doesn't exist in engine" during ANALYZE TABLE.
 * This helper rebuilds one table from db/ safely by explicit table name.
 *
 * Browser: dry-run by default; ?apply=1&table=name (Admin) rebuilds live table.
 * CLI: php scripts/repair_table_from_schema.php --table=table_name [--apply]
 */

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Table Repair Helper', ['skip_db_tests' => false]);
$nl = $boot['nl'];

$tableName = itm_apply_script_arg_value($boot['argv'], $boot['is_cli'], 'table', '');

if ($tableName === '') {
    echo 'Usage: php scripts/repair_table_from_schema.php --table=<table_name> [--apply]' . $nl;
    echo 'Browser dry-run: ?table=<table_name> then ?table=<name>&apply=1 (Admin).' . $nl;
    itm_script_output_end();
    exit(1);
}

if (!itm_is_safe_identifier($tableName)) {
    echo 'Invalid table name.' . $nl;
    itm_script_output_end();
    exit(1);
}

$sqlPath = itm_database_sql_schema_path();
if (!is_file($sqlPath)) {
    echo "db/01_schema.sql not found at expected path." . $nl;
    itm_script_output_end();
    exit(1);
}

$schemaSql = file_get_contents($sqlPath);
if ($schemaSql === false || $schemaSql === '') {
    echo 'Unable to read db/01_schema.sql content.' . $nl;
    itm_script_output_end();
    exit(1);
}

$createPattern = '/CREATE TABLE\s+`' . preg_quote($tableName, '/') . '`\s*\(.*?\)\s*ENGINE=.*?;/si';
if (!preg_match($createPattern, $schemaSql, $matches)) {
    echo "Could not find CREATE TABLE statement for '{$tableName}' in db/01_schema.sql." . $nl;
    itm_script_output_end();
    exit(1);
}

$createSql = trim((string) $matches[0]);

if (!$boot['apply']) {
    echo colorText("DRY-RUN: would DROP and recreate table `{$tableName}` from db/01_schema.sql.", 'info') . $nl;
    echo 'CREATE TABLE excerpt (first 400 chars):' . $nl;
    echo substr($createSql, 0, 400) . (strlen($createSql) > 400 ? '…' : '') . $nl;
    itm_apply_script_finish_hint(false, $boot['is_cli'], 1, $nl, 'repair_table_from_schema.php');
    itm_script_output_end();
    exit(0);
}

if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
    echo 'Database connection failed.' . $nl;
    itm_script_output_end();
    exit(1);
}

echo "Rebuilding table '{$tableName}' from db/..." . $nl;

if (!itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 0')) {
    echo 'Failed to disable FOREIGN_KEY_CHECKS.' . $nl;
    itm_script_output_end();
    exit(1);
}

$dropSql = "DROP TABLE IF EXISTS `{$tableName}`";
if (!itm_run_query($conn, $dropSql)) {
    itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
    echo "Failed to drop table '{$tableName}'." . $nl;
    itm_script_output_end();
    exit(1);
}

if (!itm_run_query($conn, $createSql)) {
    itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
    echo "Failed to recreate table '{$tableName}' from schema definition." . $nl;
    itm_script_output_end();
    exit(1);
}

if (!itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1')) {
    echo 'Warning: failed to re-enable FOREIGN_KEY_CHECKS. Please verify manually.' . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText("Table '{$tableName}' rebuilt successfully.", 'pass') . $nl;
echo 'Next step: run php scripts/analyze_database_health.php to verify.' . $nl;
itm_script_output_end();
exit(0);
