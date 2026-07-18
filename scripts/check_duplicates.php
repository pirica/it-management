<?php
/**
 * Audit database.sql for duplicate column definitions in CREATE TABLE blocks.
 *
 * Why: Duplicate column names in the same table cause SQL import errors.
 * This script identifies these duplicates before they reach the database.
 *
 * Browser: open scripts/check_duplicates.php (login required).
 * CLI: php scripts/check_duplicates.php
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

require_once __DIR__ . '/lib/script_cli_output.php';

if (!$isCli) {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Check Duplicates');
$nl = itm_script_output_nl();

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: database.sql not found at $sqlPath" . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);

$currentTable = null;
$columns = [];
$errors = [];

/**
 * Color helper (CLI only)
 */
function color(string $text, string $code): string {
    global $isCli;
    return $isCli ? "\033[" . $code . "m" . $text . "\033[0m" : $text;
}

foreach ($lines as $index => $line) {

    // Detect CREATE TABLE
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $columns = [];
    }

    if ($currentTable) {

        // Detect REAL column definitions (not index references)
        // Matches: `colname` TYPE ...
        if (preg_match('/^\s*`([^`]+)`\s+(int|bigint|varchar|char|text|timestamp|datetime|date|json|enum|tinyint|smallint|mediumint|float|double|decimal)/i', $line, $matches)) {

            $colName = $matches[1];

            if (isset($columns[$colName])) {
                $errors[$currentTable][] = [
                    'column' => $colName,
                    'line'   => $index + 1,
                    'text'   => trim($line)
                ];
            }

            $columns[$colName] = true;
        }

        // Detect end of table
        if (strpos($line, ');') !== false &&
            (strpos($line, 'ENGINE=') !== false || strpos($line, 'CHARSET=') !== false)) {
            $currentTable = null;
        }
    }
}

// Output
$total = 0;

foreach ($errors as $table => $items) {
    echo $nl . color("Table: $table", '1;31') . $nl;

    foreach ($items as $err) {
        echo "  - Duplicate column " . color("`{$err['column']}`", '1;33') .
             " on line {$err['line']}" . $nl;
        echo "    → {$err['text']}" . $nl;
        $total++;
    }
}

echo $nl . color("Total duplicate column errors: $total", '1;36') . $nl;

exit($total > 0 ? 1 : 0);

itm_script_output_end();
