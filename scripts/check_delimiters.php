<?php
$content = file_get_contents('database.sql');
$lines = explode("\n", $content);
$inTrigger = false;
$currentDelimiter = ';';
$errors = [];

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    if (preg_match('/DELIMITER\s+(\S+)/', $line, $matches)) {
        $currentDelimiter = trim($matches[1]);
        continue;
    }
    
    if (strpos($line, 'CREATE TRIGGER') !== false) {
        if ($currentDelimiter === ';') {
            $errors[] = "Trigger at line $lineNum started while DELIMITER is still ';'";
        }
        $inTrigger = true;
    }
    
    if ($inTrigger) {
        // Check for END; which is usually wrong inside a DELIMITER $$ block
        if ($currentDelimiter !== ';' && preg_match('/END;/', $line)) {
            $errors[] = "Found 'END;' at line $lineNum while DELIMITER is '$currentDelimiter'";
        }
        
        if (strpos($line, 'END' . $currentDelimiter) !== false) {
            $inTrigger = false;
        }
    }
}

if ($inTrigger) {
    $errors[] = "Reached end of file while inside a trigger block";
}

foreach ($errors as $err) echo $err . "\n";
echo "Total delimiter errors: " . count($errors) . "\n";
