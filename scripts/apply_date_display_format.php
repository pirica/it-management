<?php
/**
 * CLI-only: wire dd/mm/yyyy display into duplicated cr_render_cell_value() helpers.
 *
 * Why: Flattened CRUD modules each define cr_render_cell_value(); central helpers live in
 * includes/itm_date_format.php and must run before the final sanitize($text) return.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/apply_date_display_format.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Apply Date Display Format');

$root = dirname(__DIR__);
$needle = "    return sanitize(\$text);\n}";
$replacement = "    if (function_exists('itm_format_cell_scalar_display')) {\n        \$text = itm_format_cell_scalar_display(\$field, \$text);\n    }\n\n    return sanitize(\$text);\n}";

$changed = [];
$skipped = [];

$paths = array_merge(
    glob($root . '/modules/*/*.php') ?: [],
    glob($root . '/modules/*/includes/*.php') ?: []
);

foreach ($paths as $path) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
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
        file_put_contents($path, $updated);
        $changed[] = $rel;
    }
}

echo 'Updated ' . count($changed) . " file(s) with dd/mm/yyyy cell display.\n";
foreach ($changed as $rel) {
    echo "  - {$rel}" . $nl;
}
if ($skipped !== []) {
    echo 'Skipped ' . count($skipped) . " file(s) (already patched or different cr_render_cell_value shape).\n";
}
