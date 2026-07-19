<?php
/**
 * Static audit: modules with server-side list search must match FK/label tables.
 *
 * No per-module allowlists — every searchable module uses the same pass rules.
 *
 * CLI: php scripts/check_fk_label_search_coverage.php
 */
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('FK Label Search Coverage Check');
    echo 'CLI only: <code>php scripts/check_fk_label_search_coverage.php</code>';
    exit(0);
}

itm_script_output_begin('FK Label Search Coverage Check');
$nl = itm_script_output_nl();

$root = dirname(__DIR__);
$failures = [];

$scanRelativeFiles = [
    'index.php',
    'list_all.php',
    'index_logic.php',
    'includes/list_query.php',
    'includes/partials/render.php',
    'ajax_handler.php',
    'gallery_helpers.php',
    'events_vault_helpers.php',
    'notes_vault_helpers.php',
];

/**
 * @param string $content
 */
function fk_search_file_has_server_search($content)
{
    if (strpos($content, '$searchRaw') !== false) {
        return true;
    }
    if (preg_match('/\$_GET\[[\'"]search[\'"]\]/', $content)) {
        return true;
    }
    if (preg_match('/\$_POST\[[\'"]search[\'"]\]/', $content)) {
        return true;
    }
    if (strpos($content, '$idf_search') !== false) {
        return true;
    }
    if (preg_match('/if\s*\(\s*\$search\s*(?:!==|!=)\s*[\'"]/', $content)) {
        return true;
    }

    return false;
}

/**
 * @param string $content
 */
function fk_search_content_has_label_coverage($content)
{
    $helperMarkers = [
        'itm_crud_fk_label_search_conditions',
        'itm_crud_scalar_column_search_conditions',
        'itm_employees_build_search_conditions',
        'itm_equipment_build_search_where_sql',
        'itm_todo_build_search_clause',
        'itm_ipam_fetch_address_list',
        'itm_ipam_count_address_list',
        'itm_ipam_address_list_where_clause',
        'events_row_matches_search',
        'events_query_events_for_list',
        'notes_row_matches_search',
        'notes_query_notes_for_list',
    ];
    foreach ($helperMarkers as $marker) {
        if (strpos($content, $marker) !== false) {
            return true;
        }
    }

    if (preg_match('/EXISTS\s*\([^)]*LIKE/is', $content)) {
        return true;
    }
    if (preg_match('/\b[a-z_][a-z0-9_]*\.name\s+LIKE/i', $content)) {
        return true;
    }
    if (preg_match('/COALESCE\([^)]*\.name/i', $content)) {
        return true;
    }
    if (strpos($content, 'JSON_CONTAINS') !== false) {
        return true;
    }
    if (preg_match('/\b(?:first_name|last_name|username|display_name)\s+LIKE/i', $content)) {
        return true;
    }
    if (preg_match('/phone1_value\s+LIKE/i', $content)) {
        return true;
    }
    if (preg_match('/\blabels\s+LIKE/i', $content)) {
        return true;
    }
    if (preg_match('/password_folders/i', $content)) {
        return true;
    }
    if (preg_match('/bookmark_folders/i', $content)) {
        return true;
    }
    if (preg_match('/CONCAT\s*\(\s*COALESCE\s*\(\s*[a-z_]+\.first_name/i', $content)) {
        return true;
    }
    if (preg_match('/LEFT JOIN employees/i', $content) && preg_match('/first_name/i', $content) && preg_match('/LIKE\s*\?/i', $content)) {
        return true;
    }

    return false;
}

/**
 * Scalar-only list search (no visible FK columns in the search SQL path).
 *
 * @param string $content
 */
function fk_search_is_scalar_only_search($content)
{
    if (strpos($content, 'cr_fk_map($conn') !== false || strpos($content, 'function cr_fk_map') !== false) {
        return false;
    }
    if (strpos($content, '$fkMap') !== false) {
        return false;
    }
    if (preg_match('/foreach\s*\(\s*\$(?:fieldColumns|displayFieldColumns|uiColumns|visibleFieldColumns)\s+as\s+\$col\)/', $content)) {
        return false;
    }
    if (preg_match('/CAST\s*\([^)]*_id[^)]*AS\s+CHAR\)\s+LIKE/i', $content)) {
        return false;
    }

    return true;
}

/**
 * @param string $moduleDir
 * @param string[] $scanRelativeFiles
 * @return array<string, string>
 */
function fk_search_collect_search_files($moduleDir, array $scanRelativeFiles)
{
    $searchFiles = [];
    foreach ($scanRelativeFiles as $rel) {
        $path = $moduleDir . '/' . $rel;
        if (!is_file($path)) {
            continue;
        }
        $content = file_get_contents($path);
        if ($content === false || !fk_search_file_has_server_search($content)) {
            continue;
        }
        $searchFiles[$rel] = $content;
    }

    $listAllPath = $moduleDir . '/list_all.php';
    if (is_file($listAllPath)) {
        $listAllContent = file_get_contents($listAllPath);
        if ($listAllContent !== false
            && preg_match('/require(?:_once)?\s+[\'"]index\.php[\'"]\s*;/', $listAllContent)
            && is_file($moduleDir . '/index.php')) {
            $indexContent = file_get_contents($moduleDir . '/index.php');
            if ($indexContent !== false && fk_search_file_has_server_search($indexContent)) {
                $searchFiles['index.php'] = $indexContent;
            }
        }
    }

    return $searchFiles;
}

foreach (glob($root . '/modules/*', GLOB_ONLYDIR) as $moduleDir) {
    $slug = basename($moduleDir);
    $searchFiles = fk_search_collect_search_files($moduleDir, $scanRelativeFiles);
    if (empty($searchFiles)) {
        continue;
    }

    $merged = implode("\n", $searchFiles);
    if (fk_search_content_has_label_coverage($merged)) {
        continue;
    }
    if (fk_search_is_scalar_only_search($merged)) {
        continue;
    }

    $relativeFiles = array_keys($searchFiles);
    $failures[] = [
        'module' => $slug,
        'file' => 'modules/' . $slug . ' (' . implode(', ', $relativeFiles) . ')',
        'reason' => 'search SQL lacks FK/label coverage (shared helper, EXISTS/JOIN label LIKE, or scalar-only fields)',
    ];
}

if (empty($failures)) {
    echo "PASS: All modules with server-side search include FK/label search coverage." . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'FAIL: ' . count($failures) . " module(s) missing FK label search coverage:" . $nl;
foreach ($failures as $row) {
    echo '  - ' . $row['module'] . ' | ' . $row['file'] . ' | ' . $row['reason'] . $nl;
}
itm_script_output_end();
exit(1);
