<?php
/**
 * CLI-only: wire dd/mm/yyyy display into duplicated cr_render_cell_value() helpers.
 *
 * Why: Flattened CRUD modules each define cr_render_cell_value(); central helpers live in
 * includes/itm_date_format.php and must run before the final sanitize($text) return.
 */
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>CLI only: <code>php scripts/apply_date_display_format.php</code></p>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit(0);
}

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
    echo "  - {$rel}\n";
}
if ($skipped !== []) {
    echo 'Skipped ' . count($skipped) . " file(s) (already patched or different cr_render_cell_value shape).\n";
}
