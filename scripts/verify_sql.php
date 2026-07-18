<?php
/**
 * Comprehensive SQL audit script for database.sql.
 *
 * Why: Combines delimiter checks, duplicate column detection, and trigger
 * reference validation into a single tool.
 *
 * Browser: open scripts/verify_sql.php (login required).
 * CLI: php scripts/verify_sql.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Verify SQL');
$nl = itm_script_output_nl();

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: 'database.sql' could not be found." . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);
$lines = array_map('trim', $lines);

$tables = [];
$currentTable = null;
$errors = [];

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    
    // Detect CREATE TABLE
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = ['columns' => [], 'line' => $lineNum];
        continue;
    }
    
    if ($currentTable) {
        // Detect column definition
        if (preg_match('/^\s*`([^`]+)`/', $line, $matches)) {
            $colName = $matches[1];
            if (in_array($colName, $tables[$currentTable]['columns'])) {
                $errors[] = "Duplicate column `$colName` in table `$currentTable` at line $lineNum";
            }
            $tables[$currentTable]['columns'][] = $colName;
        }
        
        // Detect end of CREATE TABLE
        if (preg_match('/\)\s*ENGINE\s*=.*?;/', $line) || (trim($line) === ');' && !preg_match('/VALUES\s*\(/', $line))) {
             $currentTable = null;
        }
    }
}

// Now check Triggers and INSERTs
$currentDelimiter = ';';
$inTrigger = false;
$triggerTable = null;

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    
    if (preg_match('/DELIMITER\s+(\S+)/', $line, $matches)) {
        $currentDelimiter = trim($matches[1]);
        continue;
    }
    
    if (preg_match('/CREATE TRIGGER `[^`]+` (?:AFTER|BEFORE) (?:INSERT|UPDATE|DELETE) ON `([^`]+)`/', $line, $matches)) {
        $inTrigger = true;
        $triggerTable = $matches[1];
        if ($currentDelimiter === ';') {
            $errors[] = "Trigger at line $lineNum started while DELIMITER is still ';'";
        }
    }
    
    if ($inTrigger) {
        // Check column references in trigger
        if (preg_match_all('/(?:NEW|OLD)\.`([^`]+)`/', $line, $matches)) {
            foreach ($matches[1] as $col) {
                if (isset($tables[$triggerTable]) && !in_array($col, $tables[$triggerTable]['columns'])) {
                    $errors[] = "Trigger for `$triggerTable` at line $lineNum references non-existent column `$col`";
                }
            }
        }
        
        if (strpos($line, 'END' . $currentDelimiter) !== false) {
            $inTrigger = false;
        }
    }
    
    // Check INSERT statements
    if (!$inTrigger && preg_match('/INSERT INTO `([^`]+)` \(([^)]+)\) VALUES/', $line, $matches)) {
        $table = $matches[1];
        $cols = array_map(function($c) { return trim($c, ' `'); }, explode(',', $matches[2]));
        foreach ($cols as $col) {
            if (isset($tables[$table]) && !in_array($col, $tables[$table]['columns'])) {
                $errors[] = "INSERT into `$table` at line $lineNum references non-existent column `$col`";
            }
        }
    }
}

foreach ($errors as $err) {
    echo $err . $nl;
}
echo "Total errors found: " . count($errors) . $nl;

exit(count($errors) > 0 ? 1 : 0);

itm_script_output_end();
