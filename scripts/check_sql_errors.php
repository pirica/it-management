<?php
/**
 * Audit database.sql for column mismatches in triggers and INSERT statements.
 *
 * Why: Metadata drift can leave triggers referencing non-existent columns
 * or INSERT statements with wrong column counts. This script validates
 * these references against the CREATE TABLE definitions.
 *
 * Browser: open scripts/check_sql_errors.php (login required).
 * CLI: php scripts/check_sql_errors.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Check SQL Errors');

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: database.sql not found at $sqlPath\n";
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);
$lines = array_map('trim', $lines);

$tables = [];
$currentTable = null;
foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
        continue;
    }
    if ($currentTable) {
        if (preg_match('/^\s*`([^`]+)`/', $line, $matches)) {
            $tables[$currentTable][] = $matches[1];
        }
        if (preg_match('/\)\s*ENGINE\s*=.*?;/', $line) || preg_match('/\);\s*$/', $line)) {
            $currentTable = null;
        }
    }
}

$errors = [];
$currentTriggerTable = null;
$currentDelimiter = ';';
foreach ($lines as $index => $line) {
    if (preg_match('/DELIMITER\s+(\S+)/', $line, $matches)) {
        $currentDelimiter = trim($matches[1]);
        continue;
    }

    if (preg_match('/CREATE TRIGGER `[^`]+` (?:AFTER|BEFORE) (?:INSERT|UPDATE|DELETE) ON `([^`]+)`/', $line, $matches)) {
        $currentTriggerTable = $matches[1];
    }
    
    if ($currentTriggerTable && strpos($line, 'JSON_OBJECT(') !== false) {
        if (preg_match_all('/(?:NEW|OLD)\.`([^`]+)`/', $line, $matches)) {
            foreach ($matches[1] as $col) {
                if (!isset($tables[$currentTriggerTable]) || !in_array($col, $tables[$currentTriggerTable])) {
                    $errors[] = "Trigger for `$currentTriggerTable` (line " . ($index + 1) . ") references non-existent column `$col`";
                }
            }
        }
    }
    
    if ($currentTriggerTable && strpos($line, 'END' . $currentDelimiter) !== false) {
        $currentTriggerTable = null;
    }
}

// Check for duplicates in table definitions
foreach ($tables as $name => $cols) {
    $counts = array_count_values($cols);
    foreach ($counts as $col => $count) {
        if ($count > 1) {
            $errors[] = "Table `$name` has duplicate column `$col` ($count times)";
        }
    }
}

foreach ($errors as $error) {
    echo $error . "\n";
}
echo "Total errors: " . count($errors) . "\n";

exit(count($errors) > 0 ? 1 : 0);
