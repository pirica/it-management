<?php
/**
 * Add canonical $GLOBALS['fkMap'] label branch to flattened CRUD index.php modules.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="apply_crud_fk_label_display.php">dry-run</a> / <a href="apply_crud_fk_label_display.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_fk_label_display.php</code> then <code>php scripts/apply_crud_fk_label_display.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply CRUD FK label display');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$skipModules = [
    'employees',
    'registration_invitations',
    'role_assignment_rights',
    'role_hierarchy',
    'role_module_permissions',
    'employee_assignment_history',
];

$fkMapBranchMarker = "isset(\$GLOBALS['fkMap'][\$field])";

$helperFunction = <<<'PHP'
/**
 * Resolves FK label text for list/view cells.
 */
function cr_fk_label_by_id($conn, $fk, $company_id, $rawId) {
    if (function_exists('itm_fk_label_by_id')) {
        return itm_fk_label_by_id($conn, $fk, (int)$company_id, (int)$rawId);
    }

    return '';
}

PHP;

$fkMapBranch = <<<'PHP'

    if (isset($GLOBALS['fkMap'][$field])) {
        $fkRow = $GLOBALS['fkMap'][$field];
        $fkDisplayId = (int)$value;
        if ($fkDisplayId > 0 && (int)($GLOBALS['company_id'] ?? 0) > 0 && function_exists('itm_fk_resolve_company_equivalent_id')) {
            $fkDisplayId = itm_fk_resolve_company_equivalent_id($GLOBALS['conn'], $fkRow, (int)$GLOBALS['company_id'], $fkDisplayId);
        }
        $resolvedLabel = cr_fk_label_by_id($GLOBALS['conn'], $fkRow, (int)($GLOBALS['company_id'] ?? 0), $fkDisplayId);
        if ($resolvedLabel !== '') {
            return sanitize($resolvedLabel);
        }
    }

PHP;

$changed = [];
$skipped = [];
$already = [];

foreach (glob($root . '/modules/*/index.php') as $path) {
    $slug = basename(dirname($path));
    if (in_array($slug, $skipModules, true)) {
        $skipped[] = $slug . ' (manual bespoke refactor)';
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'function cr_render_cell_value') === false) {
        continue;
    }

    if (strpos($content, 'function cr_fk_map') === false && strpos($content, '$fkMap = cr_fk_map') === false) {
        continue;
    }

    if (strpos($content, $fkMapBranchMarker) !== false) {
        $already[] = $slug;
        continue;
    }

    $original = $content;

    if (strpos($content, 'function cr_fk_label_by_id') === false) {
        if (preg_match('/function\s+cr_render_cell_value\s*\(/', $content, $match, PREG_OFFSET_CAPTURE)) {
            $insertAt = (int)$match[0][1];
            $content = substr($content, 0, $insertAt) . $helperFunction . substr($content, $insertAt);
        } else {
            $skipped[] = $slug . ' (cr_render_cell_value not found for helper insert)';
            continue;
        }
    }

    $insertAt = false;
    $textAnchor = '$text = (string)($value ?? \'\');';
    $textPos = strpos($content, $textAnchor);
    if ($textPos !== false) {
        $insertAt = $textPos;
    } elseif (preg_match(
        '/if\s*\(\s*\$field\s*===\s*[\'"]active[\'"]\s*\)\s*\{[\s\S]*?\n\s*\}/',
        $content,
        $activeMatch,
        PREG_OFFSET_CAPTURE
    )) {
        $activeBlockStart = (int)$activeMatch[0][1];
        $activeBlockChunk = $activeMatch[0][0];
        $insertAt = $activeBlockStart + strlen($activeBlockChunk);
    }

    if ($insertAt === false) {
        $skipped[] = $slug . ' (insert anchor not found)';
        continue;
    }

    $content = substr($content, 0, $insertAt) . $fkMapBranch . substr($content, $insertAt);

    if (strpos($content, "\$GLOBALS['fkMap'] = \$fkMap;") === false
        && preg_match('/\$fkMap\s*=\s*cr_fk_map\s*\(\s*\$conn\s*,\s*\$crud_table\s*\)\s*;/', $content, $fkMatch, PREG_OFFSET_CAPTURE)) {
        $fkLineEnd = (int)$fkMatch[0][1] + strlen($fkMatch[0][0]);
        $content = substr($content, 0, $fkLineEnd) . "\n\$GLOBALS['fkMap'] = \$fkMap;" . substr($content, $fkLineEnd);
    }

    if ($content === $original) {
        $skipped[] = $slug . ' (no content change)';
        continue;
    }

    if ($apply) {
        file_put_contents($path, $content);
    }
    $changed[] = $slug;
}

$modeLabel = $apply ? 'Changed' : 'Would change';
echo $nl . 'FK label display apply complete.' . $nl;
echo $modeLabel . ' ' . count($changed) . ' module(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' modules', $changed);
itm_apply_script_echo_list('Already patched', $already);
itm_apply_script_echo_list('Skipped', $skipped);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_fk_label_display.php');

itm_script_output_end();
exit(0);
