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

$currentTable = null;
$tables = [];
foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
    }
    if ($currentTable && preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
        $tables[$currentTable][] = $matches[1];
    }
    if (strpos($line, ');') !== false && strpos($line, 'ENGINE=') !== false) {
        $currentTable = null;
    }
}

foreach ($tables as $table => $cols) {
    $phoneCols = array_filter($cols, function($c) { return strpos($c, 'phone') !== false; });
    if (!empty($phoneCols)) {
        echo "Table $table: " . implode(', ', $phoneCols) . "\n";
    }
}
