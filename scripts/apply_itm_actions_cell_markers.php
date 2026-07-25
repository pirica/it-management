<?php
/**
 * Add itm-actions-cell + data-itm-actions-origin="1" on Actions header/body cells.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply ITM Actions Cell Markers');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

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
$unchanged = [];

$paths = array_merge(
    glob($root . '/modules/*/index.php') ?: [],
    glob($root . '/modules/*/includes/partials/render.php') ?: []
);

foreach ($paths as $path) {
    $rel = itm_apply_script_rel_path($root, $path);
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $updated = itm_apply_actions_cell_markers($content);
    if ($updated !== $content) {
        if ($apply) {
            file_put_contents($path, $updated);
        }
        $changed[] = $rel;
    } else {
        $unchanged[] = $rel;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module list file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('Unchanged (markers already present or no Actions column)', $unchanged);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_itm_actions_cell_markers.php');

itm_script_output_end();
exit(0);
