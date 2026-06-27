<?php
/**
 * List columns for each table defined in database.sql, filtering for phone columns.
 *
 * Why: Assists in auditing table schemas and identifying PII locations.
 *
 * Browser: open scripts/list_columns.php (login required).
 * CLI: php scripts/list_columns.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

itm_script_output_begin('List Columns');
$nl = itm_script_output_nl();

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: database.sql not found at $sqlPath" . $nl;
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

echo "Scanning for phone-related columns in database.sql:" . $nl . $nl;

foreach ($tables as $table => $cols) {
    $phoneCols = array_filter($cols, function($c) { return strpos(strtolower($c), 'phone') !== false; });
    if (!empty($phoneCols)) {
        echo "Table $table: " . implode(', ', $phoneCols) . $nl;
    }
}
