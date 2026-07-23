<?php
/**
 * Count arguments in the trg_employees_audit_insert trigger In db/01_schema.sql.
 *
 * Why: Ensures that the audit trigger for employees correctly captures all
 * expected columns in its JSON_OBJECT payload.
 *
 * Browser: open scripts/count_args.php (login required).
 * CLI: php scripts/count_args.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

itm_script_output_begin('Count Args');
$nl = itm_script_output_nl();

$sqlPath = itm_database_sql_schema_path();
if (!is_file($sqlPath)) {
    echo "Error: db/01_schema.sql not found at $sqlPath" . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
if (preg_match('/CREATE TRIGGER `trg_employees_audit_insert`.*?JSON_OBJECT\((.+?)\)/s', $content, $matches)) {
    $argsPart = $matches[1];
    
    $args = [];
    $current = '';
    $depth = 0;
    $inString = false;
    for ($i = 0; $i < strlen($argsPart); $i++) {
        $char = $argsPart[$i];
        if ($char === "'" && ($i === 0 || $argsPart[$i-1] !== "\\")) $inString = !$inString;
        if (!$inString) {
            if ($char === '(') $depth++;
            if ($char === ')') $depth--;
            if ($char === ',' && $depth === 0) {
                $args[] = trim($current);
                $current = '';
                continue;
            }
        }
        $current .= $char;
    }
    $args[] = trim($current);

    echo "Total arguments in trg_employees_audit_insert: " . count($args) . $nl;
    foreach ($args as $i => $arg) {
        echo ($i+1) . ": " . $arg . $nl;
    }
} else {
    echo "Trigger trg_employees_audit_insert not found in db/03_triggers.sql01_schema.sql" . $nl;
}

itm_script_output_end();
