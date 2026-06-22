<?php
/**
 * Script to extract fields containing "by" from CREATE TABLE statements in database.sql
 */

$sqlFile = dirname(__DIR__) . '/database.sql';
if (!file_exists($sqlFile)) {
    die("database.sql not found in root directory (" . $sqlFile . ").\n");
}

$handle = fopen($sqlFile, "r");
if (!$handle) {
    die("Could not open database.sql\n");
}

$results = [];
$currentTable = null;
$inCreateTable = false;

while (($line = fgets($handle)) !== false) {
    $line = trim($line);

    // Detect start of CREATE TABLE
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $line, $match)) {
        $currentTable = $match[1];
        $inCreateTable = true;
        continue;
    }

    if ($inCreateTable) {
        // Detect end of CREATE TABLE
        if (preg_match('/^\)/', $line)) {
            $inCreateTable = false;
            $currentTable = null;
            continue;
        }

        // Match field name
        if (preg_match('/^`([^`]+)`/', $line, $fieldMatch)) {
            $fieldName = $fieldMatch[1];

            // Check if field name contains "by" (case-insensitive)
            if (stripos($fieldName, 'by') !== false) {
                if (!isset($results[$currentTable])) {
                    $results[$currentTable] = [];
                }
                $results[$currentTable][] = $fieldName;
            }
        }
    }
}

fclose($handle);

// Generate the output string
$output = "";
foreach ($results as $tableName => $fields) {
    if (empty($fields)) continue; // Extra safety
    $output .= "-- " . $tableName . "\n";
    foreach ($fields as $fieldName) {
        $output .= "- " . $fieldName . "\n";
    }
    $output .= "\n";
}

$output = trim($output);
echo $output . "\n";
