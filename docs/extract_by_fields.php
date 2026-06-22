<?php
// docs/extract_by_fields.php

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sqlFile = __DIR__ . '/../database.sql';
$outputFile = __DIR__ . '/fields_by.txt';

if (!file_exists($sqlFile)) {
    die("Error: database.sql not found at " . realpath($sqlFile ?: ''));
}

$sqlContent = file_get_contents($sqlFile);

// Keywords to search for (case-insensitive)
$keywords = ['by', 'to', 'employee_id', 'employee'];

/**
 * Parsing logic:
 * We'll search for "CREATE TABLE `table_name` ("
 * Then we'll collect all lines until we find a line starting with ")"
 * and containing "ENGINE=" or just a semicolon.
 */
$lines = explode("\n", $sqlContent);
$inTable = false;
$currentTable = "";
$currentFields = [];
$tablesData = [];

foreach ($lines as $line) {
    $trimmedLine = trim($line);

    // Check for start of CREATE TABLE
    if (preg_match('/^CREATE TABLE\s+`([^`]+)`\s*\(/i', $trimmedLine, $matches)) {
        $inTable = true;
        $currentTable = $matches[1];
        $currentFields = [];
        continue;
    }

    // Check for end of CREATE TABLE
    if ($inTable && (preg_match('/\)\s*ENGINE=/i', $trimmedLine) || preg_match('/\);\s*$/', $trimmedLine) || (preg_match('/^\)/', $trimmedLine) && !preg_match('/^\)\s*[a-zA-Z]/', $trimmedLine)))) {

        // Process collected fields
        $matchingFields = [];
        foreach ($currentFields as $field) {
            $found = false;
            foreach ($keywords as $keyword) {
                if (stripos($field, $keyword) !== false) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $matchingFields[] = $field;
            }
        }

        // Store table data
        $tablesData[$currentTable] = $matchingFields;

        $inTable = false;
        continue;
    }

    // If inside a table, collect field names
    if ($inTable) {
        // Match lines that start with a backticked field name
        if (preg_match('/^\s*`([^`]+)`\s+([a-z]+|int|varchar|timestamp|datetime|text|decimal|tinyint|enum)/i', $line, $matches)) {
            $currentFields[] = $matches[1];
        }
    }
}

// Sort tables alphabetically (ASC)
ksort($tablesData);

$output = "";
foreach ($tablesData as $tableName => $matchingFields) {
    // Format the output for this table
    $output .= "-- $tableName\n";
    foreach ($matchingFields as $field) {
        $output .= " - $field\n";
    }
    $output .= "\n\n";
}

// Save to file
if (file_put_contents($outputFile, $output) === false) {
    echo "Warning: Failed to write to $outputFile\n";
}

// Set header for browser viewing
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
}

// Display the output
echo $output;

if (php_sapi_name() === 'cli') {
    $allTablesParsed = count($tablesData);
    echo "\nResults saved to $outputFile (Total tables parsed: $allTablesParsed)\n";
}
