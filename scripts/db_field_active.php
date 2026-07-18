<?php
/**
 * DB Field Active Audit Script
 *
 * Identifies tables missing the mandatory `active` column in database.sql and
 * detects module code that references `active` on those tables.
 *
 * CLI: php scripts/db_field_active.php [--json]
 * Browser: scripts/db_field_active.php
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
    putenv('ITM_SKIP_DB_TESTS=1');
}

ob_start();
@include_once dirname(__DIR__) . '/config/config.php';
ob_end_clean();

if (!function_exists('sanitize')) {
    function sanitize($data)
    {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
$argvList = $GLOBALS['argv'] ?? [];
$asJson = in_array('--json', $argvList, true);

if (!$itmIsCli) {
    itm_script_output_begin('DB Field active audit');
    itm_script_output_close_pre();
}

$dbSqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($dbSqlPath)) {
    $message = 'Error: database.sql not found at ' . $dbSqlPath;
    if ($itmIsCli) {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        echo '<p>' . sanitize($message) . '</p>';
        itm_script_output_end();
    }
    exit(1);
}

$sqlContent = file_get_contents($dbSqlPath);
$tables = [];

if (preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=/s', $sqlContent, $matches)) {
    foreach ($matches[1] as $idx => $tableName) {
        $columnBlock = $matches[2][$idx];
        $tables[$tableName] = [
            'has_active' => (bool)preg_match('/`active`/', $columnBlock),
        ];
    }
}

$findings = [
    'missing_active_column' => [],
    'potential_code_mismatches' => [],
];

foreach ($tables as $name => $meta) {
    if (!$meta['has_active']) {
        $findings['missing_active_column'][] = $name;
    }
}

$moduleDir = dirname(__DIR__) . '/modules/';
if (is_dir($moduleDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir));
    $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

    foreach ($phpFiles as $file) {
        $filePath = $file[0];
        $content = file_get_contents($filePath);
        if (stripos($content, 'active') === false) {
            continue;
        }

        $targetTable = null;
        if (preg_match('/\$crud_table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $targetTable = $m[1];
        }

        if ($targetTable && isset($tables[$targetTable]) && !$tables[$targetTable]['has_active']) {
            if (preg_match('/\bactive\s*=\s*[0-1\?]\b/i', $content)
                || preg_match('/SELECT\b.*?\bactive\b/is', $content)) {
                $findings['potential_code_mismatches'][] = [
                    'file' => str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $filePath),
                    'table' => $targetTable,
                    'reason' => "File uses 'active' in query, but table '$targetTable' is missing it in database.sql.",
                ];
            }
        }

        foreach ($findings['missing_active_column'] as $missingTable) {
            if (preg_match('/\b' . preg_quote($missingTable, '/') . '\b.*?\bactive\b/is', $content)
                && preg_match('/(?:FROM|JOIN|UPDATE)\s+`?' . preg_quote($missingTable, '/') . '`?.*?\bactive\b/is', $content)) {
                $findings['potential_code_mismatches'][] = [
                    'file' => str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $filePath),
                    'table' => $missingTable,
                    'reason' => "Query likely references table '$missingTable' and 'active' column, but table is missing it.",
                ];
            }
        }
    }
}

$findings['potential_code_mismatches'] = array_values(array_unique(array_map('serialize', $findings['potential_code_mismatches'])));
$findings['potential_code_mismatches'] = array_map('unserialize', $findings['potential_code_mismatches']);

$tableCount = count($tables);
$missingCount = count($findings['missing_active_column']);
$mismatchCount = count($findings['potential_code_mismatches']);
$passed = ($missingCount === 0 && $mismatchCount === 0);

if ($asJson) {
    echo json_encode(
        [
            'tables_scanned' => $tableCount,
            'passed' => $passed,
            'missing_active_column' => $findings['missing_active_column'],
            'potential_code_mismatches' => $findings['potential_code_mismatches'],
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit($passed ? 0 : 1);
}

if ($itmIsCli) {
    echo colorText("DB Field 'active' Audit", 'info') . $nl;
    echo '[INFO] CREATE TABLE blocks scanned in database.sql: ' . $tableCount . $nl;
    echo '[INFO] Tables missing active column: ' . $missingCount . $nl;
    echo '[INFO] Potential code mismatches: ' . $mismatchCount . $nl . $nl;

    if ($missingCount > 0) {
        echo colorText('Tables missing \'active\' column:', 'warn') . $nl;
        foreach ($findings['missing_active_column'] as $table) {
            echo '  - ' . $table . $nl;
        }
        echo $nl;
    }

    if ($mismatchCount > 0) {
        echo colorText('Potential code mismatches:', 'warn') . $nl;
        foreach ($findings['potential_code_mismatches'] as $mismatch) {
            echo '  - ' . $mismatch['file'] . ' (table: ' . $mismatch['table'] . '): ' . $mismatch['reason'] . $nl;
        }
        echo $nl;
    }

    if ($passed) {
        echo itm_script_format_status_line('[PASS] All scanned tables include an active column; no module mismatches found.') . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] active column audit found schema or code mismatches.') . $nl;
        echo '[INFO] Fix: add `active` tinyint NOT NULL DEFAULT \'1\' in database.sql and align module queries.' . $nl;
    }

    exit($passed ? 0 : 1);
}

echo '<h1>DB Field \'active\' Audit</h1>';
echo '<p class="scripts-muted">Scans <code>database.sql</code> for compliance and identifies potential runtime errors where code expects an <code>active</code> column that does not exist.</p>';
echo '<p><strong>Tables scanned:</strong> ' . (int)$tableCount . '</p>';

if ($passed) {
    echo '<p style="color:green;font-weight:600;">[PASS] All scanned tables include an <code>active</code> column; no module mismatches found.</p>';
} else {
    echo '<p style="color:red;font-weight:600;">[FAIL] Schema or code mismatches detected — see details below.</p>';
}

echo '<h2>Tables missing \'active\' column</h2>';
if ($missingCount === 0) {
    echo '<p class="scripts-muted">None.</p>';
} else {
    echo '<ul>';
    foreach ($findings['missing_active_column'] as $table) {
        echo '<li>' . itm_script_format_table_link($table) . '</li>';
    }
    echo '</ul>';
}

echo '<h2>Potential code mismatches</h2>';
if ($mismatchCount === 0) {
    echo '<p class="scripts-muted">None.</p>';
} else {
    echo '<table class="scripts-table">';
    echo '<thead><tr><th>File</th><th>Table</th><th>Reason</th></tr></thead><tbody>';
    foreach ($findings['potential_code_mismatches'] as $mismatch) {
        $fileLink = itm_script_external_link_html('../' . $mismatch['file'], $mismatch['file']);
        $tableLink = itm_script_format_table_link($mismatch['table']);
        echo '<tr><td>' . $fileLink . '</td><td>' . $tableLink . '</td><td>' . sanitize($mismatch['reason']) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<div class="scripts-card" style="margin-top:20px;"><p class="scripts-muted">To fix: Add <code>`active` tinyint NOT NULL DEFAULT \'1\'</code> to the table definition in <code>database.sql</code> and update matching triggers.</p></div>';
}

itm_script_output_end();
exit($passed ? 0 : 1);
