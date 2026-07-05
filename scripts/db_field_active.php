<?php
/**
 * DB Field Active Audit Script
 * 
 * Purpose: Identifies tables missing the mandatory `active` column and detects
 * code mismatches where queries expect this field on tables that lack it.
 * 
 * Why: AGENTS.md mandates an `active` TINYINT column for all tables.
 * 
 * Usage:
 * Browser: scripts/db_field_active.php
 * CLI: php scripts/db_field_active.php [--json]
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');

// Try to load config but don't die on connection failure for static audit
ob_start();
@include_once __DIR__ . '/../config/config.php';
ob_end_clean();

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


$isCli = (PHP_SAPI === 'cli');
$asJson = in_array('--json', $argv ?? []);

if (!$isCli) {
    itm_script_output_begin();
}

$dbSqlPath = __DIR__ . '/../database.sql';
if (!file_exists($dbSqlPath)) {
    die("Error: database.sql not found at $dbSqlPath\n");
}

$sqlContent = file_get_contents($dbSqlPath);
$tables = [];

// Simple regex to parse table names and columns from CREATE TABLE blocks
if (preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=/s', $sqlContent, $matches)) {
    foreach ($matches[1] as $idx => $tableName) {
        $columnBlock = $matches[2][$idx];
        $hasActive = (bool)preg_match('/`active`/', $columnBlock);
        $tables[$tableName] = [
            'has_active' => $hasActive,
            'columns' => []
        ];
    }
}

$findings = [
    'missing_active_column' => [],
    'potential_code_mismatches' => []
];

foreach ($tables as $name => $meta) {
    if (!$meta['has_active']) {
        $findings['missing_active_column'][] = $name;
    }
}

// Scan modules for 'active' usage in SQL strings
$moduleDir = __DIR__ . '/../modules/';
if (is_dir($moduleDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir));
    $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

    foreach ($phpFiles as $file) {
        $filePath = $file[0];
        $content = file_get_contents($filePath);
        
        // Skip common patterns that aren't real mismatches
        // Check if the file contains "active"
        if (stripos($content, 'active') === false) {
            continue;
        }

        // Try to identify which table is being queried in this file
        // Common pattern: $crud_table = '...'
        $targetTable = null;
        if (preg_match('/\$crud_table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $targetTable = $m[1];
        }

        // If we found a usage of 'active' but the table missing it is likely the one used in the file
        if ($targetTable && isset($tables[$targetTable]) && !$tables[$targetTable]['has_active']) {
            if (preg_match('/\bactive\s*=\s*[0-1\?]\b/i', $content) || 
                preg_match('/SELECT\b.*?\bactive\b/is', $content)) {
                $findings['potential_code_mismatches'][] = [
                    'file' => str_replace(__DIR__ . '/../', '', $filePath),
                    'table' => $targetTable,
                    'reason' => "File uses 'active' in query, but table '$targetTable' is missing it in database.sql."
                ];
            }
        }
        
        // Also look for explicit table references like "FROM cable_colors ... active=1"
        foreach ($findings['missing_active_column'] as $missingTable) {
            // More strict regex to find table AND active in the same SQL-like context
            if (preg_match('/\b' . preg_quote($missingTable, '/') . '\b.*?\bactive\b/is', $content)) {
                // Heuristic: check if 'active' is likely a column of this table in the query
                if (preg_match('/(?:FROM|JOIN|UPDATE)\s+`?' . preg_quote($missingTable, '/') . '`?.*?\bactive\b/is', $content)) {
                    $findings['potential_code_mismatches'][] = [
                        'file' => str_replace(__DIR__ . '/../', '', $filePath),
                        'table' => $missingTable,
                        'reason' => "Query likely references table '$missingTable' and 'active' column, but table is missing it."
                    ];
                }
            }
        }
    }
}

// Deduplicate findings
$findings['potential_code_mismatches'] = array_map("unserialize", array_unique(array_map("serialize", $findings['potential_code_mismatches'])));

if ($asJson) {
    echo json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($isCli) {
    echo "DB Field 'active' Audit" . $nl;
    echo "========================\n" . $nl;
    
    echo "Tables missing 'active' column:" . $nl;
    foreach ($findings['missing_active_column'] as $table) {
        echo "  - $table" . $nl;
    }
    
    echo "\nPotential code mismatches:" . $nl;
    foreach ($findings['potential_code_mismatches'] as $mismatch) {
        echo "  - {$mismatch['file']} (Table: {$mismatch['table']}): {$mismatch['reason']}" . $nl;
    }
} else {
    echo "<h1>DB Field 'active' Audit</h1>";
    echo "<p class='scripts-muted'>Scans <code>database.sql</code> for compliance and identifies potential runtime errors where code expects an <code>active</code> column that doesn't exist.</p>";
    
    echo "<h2>Tables missing 'active' column</h2>";
    echo "<ul>";
    foreach ($findings['missing_active_column'] as $table) {
        $link = itm_script_format_table_link($table);
        echo "<li>$link</li>";
    }
    echo "</ul>";
    
    echo "<h2>Potential code mismatches</h2>";
    echo "<table class='scripts-table'>";
    echo "<thead><tr><th>File</th><th>Table</th><th>Reason</th></tr></thead><tbody>";
    foreach ($findings['potential_code_mismatches'] as $mismatch) {
        $modulePath = dirname($mismatch['file']) . '/';
        $fileLink = itm_script_external_link_html('../' . $mismatch['file'], $mismatch['file']);
        $tableLink = itm_script_format_table_link($mismatch['table']);
        echo "<tr><td>$fileLink</td><td>$tableLink</td><td>" . sanitize($mismatch['reason']) . "</td></tr>";
    }
    echo "</tbody></table>";
    
    echo "<div class='scripts-card' style='margin-top:20px;'><p class='scripts-muted'>To fix: Add <code>\`active\` tinyint NOT NULL DEFAULT '1'</code> to the table definition in <code>database.sql</code> and update matching triggers.</p></div>";
}
