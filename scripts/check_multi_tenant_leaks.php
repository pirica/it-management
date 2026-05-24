<?php
/**
 * Multi-Tenant Leak Checker (Optimized v2)
 * Scans modules/ for SQL queries and UI elements that might leak data across companies.
 * Supports CLI and Browser.
 */

// Configuration
$modules_dir = __DIR__ . '/../modules';
$database_sql = __DIR__ . '/../database.sql';

/**
 * Identify scoped tables (those with company_id column)
 */
function get_scoped_tables($sql_file) {
    if (!file_exists($sql_file)) return [];
    $content = file_get_contents($sql_file);
    $tables = [];

    // Match CREATE TABLE blocks
    preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE/s', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $table_name = $match[1];
        $table_def = $match[2];

        if (strpos($table_def, '`company_id`') !== false) {
            $tables[] = $table_name;
        }
    }

    return $tables;
}

$scoped_tables = get_scoped_tables($database_sql);
if (empty($scoped_tables)) {
    die("Error: No scoped tables found in $database_sql\n");
}

// Build a single regex for all scoped tables for faster initial check
$table_regex = '/\b(' . implode('|', array_map('preg_quote', $scoped_tables)) . ')\b/i';

// Scan files
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modules_dir));
$leaks = [];

foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $filepath = $file->getPathname();
    $relative_path = str_replace(realpath(__DIR__ . '/../'), '', realpath($filepath));
    $content = file_get_contents($filepath);

    // Quick check: does this file contain any scoped table?
    if (!preg_match($table_regex, $content)) {
        // Still check for UI leaks
        check_ui_leaks($content, $relative_path, $leaks);
        continue;
    }

    // Heuristic: Check if file initializes $where or $company_where with company_id
    $file_has_scoped_where = preg_match('/\$where\s*=\s*\[[^\]]*[\'"]company_id[\'"]\s*=>\s*\$company_id[^\]]*\]/', $content) ||
                             preg_match('/\$where\s*=\s*[\'"]WHERE company_id = [\'"]/', $content) ||
                             preg_match('/\$company_where\s*=\s*[\'"]WHERE company_id = [\'"]/', $content);

    // Detailed scan for queries
    // We look for common SQL query patterns
    if (preg_match_all('/([\'"])(SELECT|UPDATE|DELETE|INSERT|FROM|JOIN|INTO)\b.*?\\1/si', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $query_fragment = $match[0];
            $offset = $match[1];

            // Does this fragment mention any scoped table?
            if (preg_match($table_regex, $query_fragment, $table_match)) {
                $table = $table_match[1];

                // Ignore administrative/meta queries
                if (preg_match('/\b(DESCRIBE|SHOW|information_schema|ALTER TABLE|DROP TABLE|CREATE TABLE)\b/i', $query_fragment)) continue;

                // Determine safety of this specific fragment.
                // A query is considered safe if it has a local 'company_id' filter OR
                // if it uses a known scoped variable ($where) OR a scope-providing function.
                $has_explicit_filter = (strpos($query_fragment, 'company_id') !== false) ||
                                       (strpos($query_fragment, '$company_id') !== false) ||
                                       (strpos($query_fragment, 'itm_get_company_where') !== false);

                $uses_scoped_variable = ($file_has_scoped_where && (
                    strpos($query_fragment, '$where') !== false ||
                    strpos($query_fragment, '$company_where') !== false
                ));

                // Check the same line for parameter-based filtering (e.g. itm_run_query($sql, ['company_id' => ...]))
                $start_of_line = strrpos(substr($content, 0, $offset), "\n");
                $start_of_line = ($start_of_line === false) ? 0 : $start_of_line + 1;
                $end_of_line = strpos($content, "\n", $offset);
                $end_of_line = ($end_of_line === false) ? strlen($content) : $end_of_line;
                $line_content = substr($content, $start_of_line, $end_of_line - $start_of_line);
                $has_line_filter = (strpos($line_content, 'company_id') !== false ||
                                    strpos($line_content, 'itm_get_company_where') !== false ||
                                    ($file_has_scoped_where && (strpos($line_content, '$where') !== false || strpos($line_content, '$company_where') !== false)));

                if (!$has_explicit_filter && !$uses_scoped_variable && !$has_line_filter) {
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $leaks[] = [
                        'file' => $relative_path,
                        'line' => $line,
                        'table' => $table,
                        'type' => 'Missing company_id filter',
                        'snippet' => (strlen($query_fragment) > 120) ? substr($query_fragment, 0, 120) . '...' : $query_fragment
                    ];
                }

                // Check for INSERTs missing company_id
                if (preg_match('/INSERT\s+INTO\s+[`]?'.preg_quote($table).'[`]?\s*\(([^)]+)\)/i', $query_fragment, $cols)) {
                    if (strpos($cols[1], 'company_id') === false) {
                        $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                        $leaks[] = [
                            'file' => $relative_path,
                            'line' => $line,
                            'table' => $table,
                            'type' => 'INSERT missing company_id',
                            'snippet' => $query_fragment
                        ];
                    }
                }
            }
        }
    }

    // Check for UI leaks
    check_ui_leaks($content, $relative_path, $leaks);
}

function check_ui_leaks($content, $relative_path, &$leaks) {
    if (strpos($content, 'Company ID') !== false) {
        if (preg_match_all('/<label[^>]*>Company ID<\/label>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                // Ignore if it's commented out or appears to be part of a hidden input block
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $leaks[] = [
                    'file' => $relative_path,
                    'line' => $line,
                    'table' => 'N/A (UI)',
                    'type' => 'Visible "Company ID" label',
                    'snippet' => $match[0]
                ];
            }
        }
    }
}

// Output
$is_cli = PHP_SAPI === 'cli';

if (!$is_cli) {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    echo "<html><head><title>Multi-Tenant Leak Audit</title>";
    echo "<style>body{font-family:sans-serif;background:#f4f4f4;padding:20px;} table{width:100%;border-collapse:collapse;background:white;} th,td{padding:10px;border:1px solid #ddd;text-align:left;} th{background:#eee;} .type-err{color:red;font-weight:bold;} code{background:#fffbe6;padding:2px 4px;border:1px solid #ffe58f;}</style>";
    echo "</head><body>";
    itm_script_browser_nav_echo();
    echo "<h1>Multi-Tenant Leak Audit Result</h1>";
    echo "<p>Scoped tables identified from database.sql: " . count($scoped_tables) . "</p>";
} else {
    echo "Multi-Tenant Leak Audit\n";
    echo "========================\n";
    echo "Scoped tables identified: " . count($scoped_tables) . "\n\n";
}

if (empty($leaks)) {
    echo "No leaks detected! (Based on current heuristics)\n";
} else {
    if (!$is_cli) {
        echo "<table><tr><th>File</th><th>Line</th><th>Table</th><th>Issue Type</th><th>Snippet</th></tr>";
        foreach ($leaks as $leak) {
            echo "<tr><td>{$leak['file']}</td><td>{$leak['line']}</td><td>{$leak['table']}</td><td class='type-err'>{$leak['type']}</td><td><code>" . htmlspecialchars($leak['snippet']) . "</code></td></tr>";
        }
        echo "</table>";
    } else {
        foreach ($leaks as $leak) {
            echo "[!] {$leak['file']}:{$leak['line']} - {$leak['type']} (Table: {$leak['table']})\n";
            echo "    Snippet: " . str_replace(["\r", "\n"], ' ', $leak['snippet']) . "\n\n";
        }
        echo "Total issues found: " . count($leaks) . "\n";
    }
}

if (!$is_cli) echo "</body></html>";
