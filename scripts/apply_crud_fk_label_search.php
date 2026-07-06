<?php
/**
 * CLI-only: extend flattened CRUD index search with FK label EXISTS predicates.
 *
 * Why: List cells render FK labels via cr_render_cell_value(), but search only matched raw IDs.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/apply_crud_fk_label_search.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Apply CRUD FK Label Search');

$root = dirname(__DIR__);
$marker = 'itm_crud_fk_label_search_conditions';
$skipModules = ['employees'];
$requireLine = "require_once '../../includes/itm_crud_fk_label_search.php';\n";

$changed = [];
$skipped = [];
$already = [];

/**
 * @return array{0: string, 1: string}|null foreach var and alias
 */
function apply_crud_fk_detect_search_loop($searchChunk)
{
    $foreachVar = null;
    foreach (['$fieldColumns', '$displayFieldColumns', '$uiColumns', '$visibleFieldColumns'] as $candidate) {
        if (strpos($searchChunk, 'foreach (' . $candidate . ' as $col)') !== false) {
            $foreachVar = $candidate;
            break;
        }
    }
    if ($foreachVar === null) {
        return null;
    }

    $alias = (strpos($searchChunk, 'CAST(e.') !== false || strpos($searchChunk, 'CAST(e.`') !== false) ? "'e'" : "''";

    return [$foreachVar, $alias];
}

foreach (glob($root . '/modules/*/index.php') as $path) {
    $slug = basename(dirname($path));
    if (in_array($slug, $skipModules, true)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'function cr_fk_map') === false) {
        continue;
    }

    if (strpos($content, $marker) !== false) {
        $already[] = $slug;
        continue;
    }

    if (strpos($content, '$searchConditions') === false || strpos($content, '$searchEsc') === false) {
        $skipped[] = $slug . ' (no search block)';
        continue;
    }

    $original = $content;

    if (strpos($content, 'includes/itm_crud_fk_label_search.php') === false) {
        if (!preg_match("/require(?:_once)?\\s+['\"]\\.\\.\\/\\.\\.\\/config\\/config\\.php['\"];\\n/", $content)) {
            $skipped[] = $slug . ' (config require not found)';
            continue;
        }
        $content = preg_replace(
            "/(require(?:_once)?\\s+['\"]\\.\\.\\/\\.\\.\\/config\\/config\\.php['\"];\\n)/",
            '$1' . $requireLine,
            $content,
            1
        );
    }

    $escPos = strpos($content, '$searchEsc = mysqli_real_escape_string($conn, $searchPattern);');
    if ($escPos === false) {
        $escPos = strpos($content, '$searchEsc = mysqli_real_escape_string($conn, \'%\' . $searchRaw . \'%\');');
    }
    if ($escPos === false) {
        $skipped[] = $slug . ' (searchEsc assignment not found)';
        continue;
    }

    $ifPos = strpos($content, 'if (!empty($searchConditions))', $escPos);
    $injectBeforePos = false;
    if ($ifPos === false) {
        if (preg_match('/\$where\s*\.=\s*["\'] AND \(" \. implode\(\' OR \', \$searchConditions\)/', $searchChunk = substr($content, $escPos, 800), $wm, PREG_OFFSET_CAPTURE)) {
            $injectBeforePos = $escPos + $wm[0][1];
        } else {
            $skipped[] = $slug . ' (search if-block not found)';
            continue;
        }
    }

    $searchChunk = substr($content, $escPos, ($injectBeforePos !== false ? $injectBeforePos : $ifPos) - $escPos);
    $detected = apply_crud_fk_detect_search_loop($searchChunk);
    if ($detected === null) {
        $skipped[] = $slug . ' (search foreach not found)';
        continue;
    }

    [$foreachVar, $alias] = $detected;
    $injection = "\n    \$itmFkSearchFields = [];\n    foreach ({$foreachVar} as \$col) {\n        \$itmFkFieldName = (string)(\$col['Field'] ?? '');\n        if (\$itmFkFieldName !== '') {\n            \$itmFkSearchFields[] = \$itmFkFieldName;\n        }\n    }\n    if (!empty(\$fkMap)) {\n        \$itmFkLabelSearch = itm_crud_fk_label_search_conditions(\$conn, \$crud_table, {$alias}, \$fkMap, \$itmFkSearchFields, (int)\$company_id, \$searchEsc);\n        if (!empty(\$itmFkLabelSearch)) {\n            \$searchConditions = array_merge(\$searchConditions, \$itmFkLabelSearch);\n        }\n    }\n\n";

    $insertAt = ($injectBeforePos !== false) ? $injectBeforePos : $ifPos;
    $content = substr($content, 0, $insertAt) . $injection . substr($content, $insertAt);

    if ($content === $original) {
        $skipped[] = $slug . ' (no content change)';
        continue;
    }

    file_put_contents($path, $content);
    $changed[] = $slug;
}

echo 'FK label search apply complete.' . $nl;
echo 'Changed: ' . count($changed) . $nl;
foreach ($changed as $slug) {
    echo '  - ' . $slug . $nl;
}
echo 'Already patched: ' . count($already) . $nl;
echo 'Skipped: ' . count($skipped) . $nl;
foreach ($skipped as $line) {
    echo '  - ' . $line . $nl;
}

itm_script_output_end();
exit(0);
