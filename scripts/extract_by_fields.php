<?php
/**
 * Extract Table Fields by Keywords
 *
 * Why: Scans database.sql to extract table column definitions matching specific keywords
 * (by, to, employee_id, employee) and outputs them in a standardized schema report.
 *
 * Browser: open while logged in as Admin.
 * CLI: php scripts/extract_by_fields.php
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

itm_script_output_begin('Extract Table Fields by Keywords');

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$nl = itm_script_output_nl();

if (!$isCli) {
    echo "<h2>Extract Table Fields by Keywords</h2>";
    echo "<p class=\"report-muted\">Scans database.sql and lists fields containing keywords: <code>by</code>, <code>to</code>, <code>employee_id</code>, <code>employee</code>.</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border-radius:4px; overflow-x:auto; max-height: 600px;'>";
}

$sqlFile = ROOT_PATH . 'database.sql';
$outputFile = ROOT_PATH . 'scripts/fields_by.txt';

if (!file_exists($sqlFile)) {
    die("Error: database.sql not found." . $nl);
}

$sqlContent = file_get_contents($sqlFile);

// Keywords to search for (case-insensitive)
$keywords = ['by', 'to', 'employee_id', 'employee'];

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
    echo "Warning: Failed to write to " . $outputFile . $nl;
}

echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');

if (!$isCli) {
    echo "</pre>";
} else {
    $allTablesParsed = count($tablesData);
    echo "\nResults saved to fields_by.txt (Total tables parsed: $allTablesParsed)\n";
}

itm_script_output_end();
