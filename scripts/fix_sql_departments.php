<?php
/**
 * Fix column count mismatch in departments INSERT statements in db/01_schema.sql.
 *
 * Browser: dry-run by default; ?apply=1 (Admin) writes db/01_schema.sql.
 * CLI: php scripts/fix_sql_departments.php then php scripts/fix_sql_departments.php --apply
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_fix_script_report.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Fix SQL Departments');
$nl = $boot['nl'];

$sqlFile = itm_database_sql_schema_path();

if (!file_exists($sqlFile)) {
    echo "Error: db/01_schema.sql could not be found." . $nl;
    itm_script_output_end();
    exit(1);
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
$sqlBundleItems = [];
$fixItems = [];
$lineNumber = 0;

foreach ($lines as $line) {
    $lineNumber++;
    if (preg_match('/^INSERT INTO `departments` \(`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`\) VALUES \((.+)\);$/', trim($line), $matches)) {
        $valuesPart = $matches[1];
        $values = parseValues($valuesPart);

        if (count($values) !== 11) {
            $sqlBundleItems[] = 'db/01_schema.sql line ' . $lineNumber . ': departments INSERT has '
                . count($values) . ' values (expected 11)';
            $fixItems[] = 'db/01_schema.sql line ' . $lineNumber . ': normalize departments INSERT column count';
            $newValues = [
                $values[0],
                $values[1],
                $values[2],
                $values[3],
                $values[4],
                $values[5],
                $values[6],
                $values[7],
                $values[8],
                $values[count($values) - 2],
                $values[count($values) - 1],
            ];
            $line = "INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (" . implode(", ", $newValues) . ");";
            $fixedCount++;
        }
    }
    $newLines[] = $line;
}

if ($fixedCount > 0 && $boot['apply']) {
    file_put_contents($sqlFile, implode("\n", $newLines));
}

itm_fix_script_report_finish(
    $boot['apply'],
    $boot['is_cli'],
    $fixedCount > 0,
    $nl,
    'fix_sql_departments.php',
    [itm_fix_script_report_na_item()],
    $sqlBundleItems,
    $fixItems
);

itm_script_output_end();
