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

$currentTable = null;
$columns = [];
$errors = [];

foreach ($lines as $index => $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $columns = [];
    }
    
    if ($currentTable) {
        if (preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
            $colName = $matches[1];
            if (isset($columns[$colName])) {
                $errors[] = "Duplicate column `$colName` in table `$currentTable` on line " . ($index + 1);
            }
            $columns[$colName] = true;
        }
        
        if (strpos($line, ');') !== false && strpos($line, 'ENGINE=') !== false) {
            $currentTable = null;
        }
    }
}

foreach ($errors as $error) {
    echo $error . "\n";
}
echo "Total duplicate column errors: " . count($errors) . "\n";
