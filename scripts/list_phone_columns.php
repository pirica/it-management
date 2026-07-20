<?php
/**
 * List columns for each table defined in db/, filtering for phone columns.
 *
 * Why: Assists in auditing table schemas and identifying PII locations.
 *
 * Browser: open scripts/list_columns.php (login required).
 * CLI: php scripts/list_columns.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('List Columns');
$nl = itm_script_output_nl();

$sqlPath = itm_database_sql_schema_path();
if (!is_file($sqlPath)) {
    echo "Error: db/01_schema.sql not found at $sqlPath" . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);
$lines = array_map('trim', $lines);

$currentTable = null;
$tables = [];
foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
    }
    if ($currentTable && preg_match('/^\s*`([^`]+)`/', $line, $matches)) {
        $tables[$currentTable][] = $matches[1];
    }
    if (strpos($line, ');') !== false && (strpos($line, 'ENGINE=') !== false || strpos($line, 'CHARSET=') !== false)) {
        $currentTable = null;
    }
}

echo "Scanning for phone-related columns in db/:" . $nl . $nl;

foreach ($tables as $table => $cols) {
    $phoneCols = array_filter($cols, function($c) { return strpos(strtolower($c), 'phone') !== false; });
    if (!empty($phoneCols)) {
        echo "Table $table: " . implode(', ', $phoneCols) . $nl;
    }
}

itm_script_output_end();
