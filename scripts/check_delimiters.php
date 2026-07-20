<?php
/**
 * Audit db/ for correct DELIMITER usage in trigger blocks.
 *
 * Why: Triggers in db/ require custom delimiters ($$) to allow
 * internal semicolons. This script identifies blocks missing delimiters
 * or using incorrect END sequences.
 *
 * Browser: open scripts/check_delimiters.php (login required).
 * CLI: php scripts/check_delimiters.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Check Delimiters');
$nl = itm_script_output_nl();

$sqlPath = itm_database_sql_schema_path();
if (!is_file($sqlPath)) {
    echo "Error: db/01_schema.sql not found at $sqlPath" . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
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

foreach ($errors as $err) {
    echo $err . $nl;
}
echo "Total delimiter errors: " . count($errors) . $nl;

exit(count($errors) > 0 ? 1 : 0);

itm_script_output_end();
