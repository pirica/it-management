<?php
// 1. Define the path to the database file in the previous folder
$sqlFile = '../database.sql';

// 2. Make sure the file actually exists before trying to read it
if (file_exists($sqlFile)) {
    
    // 3. Read the entire file content into a string
    $content = file_get_contents($sqlFile);
    
    // 4. Explode the content into an array by newline characters
    $lines = explode("\n", $content);
    
    // 5. Clean up any hidden carriage returns (\r) left over from Windows formatting
    $lines = array_map('trim', $lines);

    // ---- YOUR CODE GOES HERE ----
    // The $lines array is now ready for you to use.
    // For example, you can see how many lines were loaded:
    echo "Successfully loaded " . count($lines) . " lines from the SQL file.";

} else {
    // Elegant fallback if the file path is incorrect
    die("Error: 'database.sql' could not be found in the previous folder.");
}

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
        if (preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
            $colName = $matches[1];
            if (in_array($colName, $tables[$currentTable]['columns'])) {
                $errors[] = "Duplicate column `$colName` in table `$currentTable` at line $lineNum";
            }
            $tables[$currentTable]['columns'][] = $colName;
        }
        
        // Detect end of CREATE TABLE
        // Usually ends with ) ENGINE=...;
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
        // Check for specific columns in JSON_OBJECT
        if (strpos($line, 'JSON_OBJECT(') !== false) {
             if (preg_match_all("/'([^']+)', (?:NEW|OLD)\.`[^`]+`/", $line, $matches)) {
                 foreach ($matches[1] as $col) {
                     if (isset($tables[$triggerTable]) && !in_array($col, $tables[$triggerTable]['columns'])) {
                        // Sometimes the key in JSON doesn't match the column name exactly, but here it usually does
                        // Skip if it's a known mismatch or just report it
                        // Actually, in this project they should match.
                        $errors[] = "Trigger for `$triggerTable` at line $lineNum has JSON key '$col' which is not a column";
                     }
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

foreach ($errors as $err) echo $err . "\n";
echo "Total errors found: " . count($errors) . "\n";
