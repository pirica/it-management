<?php
/**
 * Fix column count mismatch in departments INSERT statements in database.sql.
 *
 * Why: Manual edits to departments table schema can leave seed data with
 * mismatched column counts. This script normalizes these INSERTs to 11 columns.
 *
 * CLI: php scripts/fix_sql_departments.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Fix SQL Departments</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong></p><pre>php scripts/fix_sql_departments.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);

$sqlFile = dirname(__DIR__) . '/database.sql';

if (!file_exists($sqlFile)) {
    die("Error: 'database.sql' could not be found.\n");
}

$content = file_get_contents($sqlFile);
$lines = explode("\n", $content);

/**
 * @param string $valuesPart
 * @return array<int, string>
 */
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
foreach ($lines as $line) {
    if (preg_match('/^INSERT INTO `departments` \(`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`\) VALUES \((.+)\);$/', trim($line), $matches)) {
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
                $values[7], // dect
                $values[8], // extension
                $values[count($values) - 2], // active
                $values[count($values) - 1]  // created_at
            ];
            $line = "INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (" . implode(", ", $newValues) . ");";
            $fixedCount++;
        }
    }
    $newLines[] = $line;
}

if ($fixedCount > 0) {
    file_put_contents($sqlFile, implode("\n", $newLines));
    echo "Fixed $fixedCount lines in database.sql." . $nl;
} else {
    echo "No departments INSERT lines needed fixing." . $nl;
}

itm_script_output_end();
