<?php
/**
 * Wire dd/mm/yyyy display into duplicated cr_render_cell_value() helpers.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply Date Display Format');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$needle = "    return sanitize(\$text);\n}";
$replacement = "    if (function_exists('itm_format_cell_scalar_display')) {\n        \$text = itm_format_cell_scalar_display(\$field, \$text);\n    }\n\n    return sanitize(\$text);\n}";

$changed = [];
$skipped = [];

$paths = array_merge(
    glob($root . '/modules/*/*.php') ?: [],
    glob($root . '/modules/*/includes/*.php') ?: []
);

foreach ($paths as $path) {
    $rel = itm_apply_script_rel_path($root, $path);
    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'function cr_render_cell_value') === false) {
        continue;
    }
    if (strpos($content, 'itm_format_cell_scalar_display') !== false) {
        $skipped[] = $rel;
        continue;
    }
    if (strpos($content, $needle) === false) {
        $skipped[] = $rel . ' (pattern mismatch)';
        continue;
    }

    $updated = str_replace($needle, $replacement, $content);
    if ($updated !== $content) {
        if ($apply) {
            file_put_contents($path, $updated);
        }
        $changed[] = $rel;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' file(s) with dd/mm/yyyy cell display.' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('Skipped (already patched or different shape)', $skipped);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_date_display_format.php');

itm_script_output_end();
exit(0);
