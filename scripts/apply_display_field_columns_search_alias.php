<?php
/**
 * CLI-only: ensure $displayFieldColumns is defined before list/search use in CRUD index.php files.
 *
 * Why: Many flattened modules copied a search loop using $displayFieldColumns without assigning it
 * (only $uiColumns exists). Module browser QA search step logged hundreds of notices otherwise.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/apply_display_field_columns_search_alias.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Apply Display Field Columns Search Alias');

$root = dirname(__DIR__);
$aliasUi = "// Why: Search and list share visible columns; alias matches role/ui_configuration modules.\n\$displayFieldColumns = \$uiColumns;\n\n";
$aliasVisible = "// Why: Search uses the same visible column set as the list table.\n\$displayFieldColumns = \$visibleFieldColumns;\n\n";
$moduleMarker = "\n\$modulePath = dirname(\$_SERVER['PHP_SELF']);";
$searchLoopOld = "    \$searchConditions = [\"CAST(`id` AS CHAR) LIKE '{\$searchEsc}'\"];\n    foreach (\$uiColumns as \$col) {";
$searchLoopNew = "    \$searchConditions = [\"CAST(`id` AS CHAR) LIKE '{\$searchEsc}'\"];\n    foreach (\$displayFieldColumns as \$col) {";
$searchInlineAssign = "    \$displayFieldColumns = \$uiColumns;\n    foreach (\$displayFieldColumns as \$col) {";

$changed = [];

foreach (glob($root . '/modules/*/index.php') as $path) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $original = $content;

    $markerPos = strpos($content, $moduleMarker);
    if ($markerPos !== false) {
        $insert = null;
        if (strpos($content, '$visibleFieldColumns =') !== false && strpos($content, '$displayFieldColumns = $visibleFieldColumns') === false) {
            $insert = "\n" . $aliasVisible . ltrim($moduleMarker, "\n");
        } elseif (strpos($content, '$uiColumns =') !== false && !preg_match('/^\s*\$displayFieldColumns\s*=\s*\$uiColumns\s*;/m', $content)) {
            $insert = "\n" . $aliasUi . ltrim($moduleMarker, "\n");
        }
        if ($insert !== null) {
            $content = substr_replace($content, $insert, $markerPos, strlen($moduleMarker));
        }
    }

    if (strpos($content, $searchLoopOld) !== false) {
        $content = str_replace($searchLoopOld, $searchLoopNew, $content);
    }

    if (strpos($content, $searchInlineAssign) !== false) {
        $content = str_replace($searchInlineAssign, "    foreach (\$displayFieldColumns as \$col) {", $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changed[] = $rel;
    }
}

echo 'Updated ' . count($changed) . " module index.php file(s).\n";
foreach ($changed as $rel) {
    echo "  - {$rel}" . $nl;
}

itm_script_output_end();
