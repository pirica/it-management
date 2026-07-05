<?php
/**
 * CLI-only: add itm-actions-cell + data-itm-actions-origin="1" on Actions header/body cells.
 *
 * Why: Module browser QA ui_check and the global table-actions layout engine require both markers
 * on the Actions column header and at least one tbody data row.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/apply_itm_actions_cell_markers.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Apply ITM Actions Cell Markers');

$root = dirname(__DIR__);

/**
 * @param string $attrs Existing attribute string (leading space optional)
 * @param string $extraClass Class token to ensure on class=""
 */
function itm_actions_merge_td_attrs(string $attrs, string $extraClass = 'itm-actions-cell'): string
{
    if (!preg_match('/\b' . preg_quote($extraClass, '/') . '\b/i', $attrs)) {
        if (preg_match('/\bclass=(["\'])([^"\']*)\1/i', $attrs, $cm)) {
            $quote = $cm[1];
            $classes = trim($cm[2] . ' ' . $extraClass);
            $attrs = preg_replace('/\bclass=(["\'])([^"\']*)\1/i', 'class=' . $quote . $classes . $quote, $attrs, 1);
        } else {
            $attrs .= ' class="' . $extraClass . '"';
        }
    }
    if (!preg_match('/\bdata-itm-actions-origin=(["\'])1\1/i', $attrs)) {
        $attrs .= ' data-itm-actions-origin="1"';
    }

    return $attrs;
}

/**
 * @param string $content index.php source
 */
function itm_apply_actions_cell_markers(string $content): string
{
    if (stripos($content, 'Actions') === false) {
        return $content;
    }

    $content = preg_replace_callback(
        '/<th\b([^>]*)>\s*Actions\s*<\/th>/i',
        static function (array $m): string {
            return '<th' . itm_actions_merge_td_attrs($m[1]) . '>Actions</th>';
        },
        $content
    ) ?? $content;

    $content = preg_replace_callback(
        '/<td\b([^>]*\bitm-actions-cell[^>]*)>/i',
        static function (array $m): string {
            if (preg_match('/\bdata-itm-actions-origin=(["\'])1\1/i', $m[1])) {
                return $m[0];
            }

            return '<td' . $m[1] . ' data-itm-actions-origin="1">';
        },
        $content
    ) ?? $content;

    // Actions column immediately after the list column loop (avoids matching unrelated <td> cells).
    $content = preg_replace_callback(
        '/(<\?php endforeach; \?>\s*<td)((?![^>]*\bitm-actions-cell\b)[^>]*)(>\s*<div class="itm-actions-wrap")/i',
        static function (array $m): string {
            return $m[1] . itm_actions_merge_td_attrs($m[2]) . $m[3];
        },
        $content
    ) ?? $content;

    $content = preg_replace_callback(
        '/(<\?php endforeach; \?>\s*<td)((?![^>]*\bitm-actions-cell\b)[^>]*)(>\s*<a class="btn btn-sm" href="view\.php\?id=)/i',
        static function (array $m): string {
            return $m[1] . itm_actions_merge_td_attrs($m[2]) . $m[3];
        },
        $content
    ) ?? $content;

    return $content;
}

$changed = [];

$paths = array_merge(
    glob($root . '/modules/*/index.php') ?: [],
    glob($root . '/modules/*/includes/partials/render.php') ?: []
);

foreach ($paths as $path) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $updated = itm_apply_actions_cell_markers($content);
    if ($updated !== $content) {
        file_put_contents($path, $updated);
        $changed[] = $rel;
    }
}

echo 'Updated ' . count($changed) . " module list file(s).\n";
foreach ($changed as $rel) {
    echo "  - {$rel}" . $nl;
}

itm_script_output_end();
