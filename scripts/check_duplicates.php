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

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Check Duplicates');

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: database.sql not found at $sqlPath\n";
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);
$lines = array_map('trim', $lines);

$currentTable = null;
$columns = [];
$errors = [];

foreach ($lines as $index => $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $columns = [];
    }
    
    if ($currentTable) {
        if (preg_match('/^\s*`([^`]+)`/', $line, $matches)) {
            $colName = $matches[1];
            if (isset($columns[$colName])) {
                $errors[] = "Duplicate column `$colName` in table `$currentTable` on line " . ($index + 1);
            }
            $columns[$colName] = true;
        }
        
        if (strpos($line, ');') !== false && (strpos($line, 'ENGINE=') !== false || strpos($line, 'CHARSET=') !== false)) {
            $currentTable = null;
        }
    }
}

foreach ($errors as $error) {
    echo $error . "\n";
}
echo "Total duplicate column errors: " . count($errors) . "\n";

exit(count($errors) > 0 ? 1 : 0);
