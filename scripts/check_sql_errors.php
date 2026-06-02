<?php
// Use '../' to look in the previous (parent) directory
$sqlFile = '../database.sql';

// Check if the file actually exists before trying to read it
if (file_exists($sqlFile)) {
    $content = file_get_contents($sqlFile);
    $lines = explode("\n", $content);
    
    // Optional: Clean up carriage returns if the file was saved on Windows
    $lines = array_map('trim', $lines);
    
    echo "Successfully loaded " . count($lines) . " lines.";
} else {
    echo "Error: Could not find the file at " . realpath($sqlFile);
}

$content = file_get_contents($sqlFile);
$lines = explode("\n", $content);

$tables = [];
$currentTable = null;
foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
        continue;
    }
    if ($currentTable) {
        if (preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
            $tables[$currentTable][] = $matches[1];
        }
        if (preg_match('/\)\s*ENGINE\s*=.*?;/', $line) || preg_match('/\);\s*$/', $line)) {
            $currentTable = null;
        }
    }
}

$errors = [];
$currentTriggerTable = null;
foreach ($lines as $index => $line) {
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
    
    if (strpos($line, 'END$$') !== false) {
        $currentTriggerTable = null;
    }
}

// Check for duplicates
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
