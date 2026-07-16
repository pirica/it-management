<?php
/**
 * Ensure $displayFieldColumns is defined before list/search use in CRUD index.php files.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 *
 * Usage:
 *   php scripts/apply_display_field_columns_search_alias.php
 *   php scripts/apply_display_field_columns_search_alias.php --apply
 *   Browser: scripts/apply_display_field_columns_search_alias.php
 *   Browser apply: scripts/apply_display_field_columns_search_alias.php?apply=1
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply Display Field Columns Search Alias');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$aliasUi = "// Why: Search and list share visible columns; alias matches role/ui_configuration modules.\n\$displayFieldColumns = \$uiColumns;\n\n";
$aliasVisible = "// Why: Search uses the same visible column set as the list table.\n\$displayFieldColumns = \$visibleFieldColumns;\n\n";
$moduleMarker = "\n\$modulePath = dirname(\$_SERVER['PHP_SELF']);";
$searchLoopOld = "    \$searchConditions = [\"CAST(`id` AS CHAR) LIKE '{\$searchEsc}'\"];\n    foreach (\$uiColumns as \$col) {";
$searchLoopNew = "    \$searchConditions = [\"CAST(`id` AS CHAR) LIKE '{\$searchEsc}'\"];\n    foreach (\$displayFieldColumns as \$col) {";
$searchInlineAssign = "    \$displayFieldColumns = \$uiColumns;\n    foreach (\$displayFieldColumns as \$col) {";

$changed = [];
$unchanged = [];

foreach (glob($root . '/modules/*/index.php') as $path) {
    $rel = itm_apply_script_rel_path($root, $path);
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
        if ($apply) {
            file_put_contents($path, $content);
        }
        $changed[] = $rel;
    } else {
        $unchanged[] = $rel;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module index.php file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' modules', $changed);
itm_apply_script_echo_list('Unchanged (no patch needed)', $unchanged);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_display_field_columns_search_alias.php');

itm_script_output_end();
exit(0);
