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

function parseValues($valuesPart) {
    $values = [];
    $currentValue = '';
    $inString = false;
    $quoteChar = '';
    for ($i = 0; $i < strlen($valuesPart); $i++) {
        $char = $valuesPart[$i];
        if ($char === "'" && ($i === 0 || $valuesPart[$i-1] !== "\\")) {
            if (!$inString) {
                $inString = true;
                $quoteChar = "'";
            } elseif ($quoteChar === "'") {
                $inString = false;
            }
        }
        if ($char === "," && !$inString) {
            $values[] = trim($currentValue);
            $currentValue = '';
        } else {
            $currentValue .= $char;
        }
    }
    $values[] = trim($currentValue);
    return $values;
}

$newLines = [];
$fixedCount = 0;
foreach ($lines as $index => $line) {
    if (preg_match('/^INSERT INTO `departments` \(`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `deck`, `extension`, `active`, `created_at`\) VALUES \((.+)\);$/', $line, $matches)) {
        $valuesPart = $matches[1];
        $values = parseValues($valuesPart);
        
        if (count($values) !== 11) {
            $newValues = [
                $values[0], // id
                $values[1], // company_id
                $values[2], // name
                $values[3], // code
                $values[4], // description
                $values[5], // email
                $values[6], // phone
                $values[7], // deck
                $values[8], // extension
                $values[count($values) - 2], // active
                $values[count($values) - 1]  // created_at
            ];
            $line = "INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `deck`, `extension`, `active`, `created_at`) VALUES (" . implode(", ", $newValues) . ");";
            $fixedCount++;
        }
    }
    $newLines[] = $line;
}

file_put_contents($sqlFile, implode("\n", $newLines));
echo "Fixed $fixedCount lines in $sqlFile.\n";
