<?php
/**
 * Normalize list-header create.php ➕ controls to canonical markup + shared CSS class.
 *
 * Why: fields_missing New button style gate expects href create.php, btn btn-primary,
 * title="Create", emoji-only visible label, and styles.css 40×40 footprint.
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';

$boot = itm_apply_script_bootstrap('Apply list new button style');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$scanned = 0;
$skippedPass = 0;
$skippedNoCreate = 0;
$changed = [];

/**
 * @return string|null Updated anchor HTML or null when unchanged / not an emoji list create control.
 */
function itm_apply_normalize_list_new_button_anchor(string $anchorHtml): ?string
{
    if (!itm_ui_index_anchor_is_emoji_only_list_new_button($anchorHtml)) {
        return null;
    }
    if (preg_match(itm_ui_index_canonical_list_new_button_pattern(), $anchorHtml) === 1
        && stripos($anchorHtml, 'itm-list-new-button') !== false
    ) {
        return null;
    }
    if (preg_match('/\bbtn-sm\b/i', $anchorHtml) === 1) {
        $anchorHtml = preg_replace('/\bbtn-sm\s*/i', '', $anchorHtml) ?? $anchorHtml;
    }

    $updated = preg_replace_callback(
        '/<a\b([^>]*)>(\s*➕\s*)<\/a>/iu',
        static function (array $matches): string {
            $attrs = $matches[1];
            if (preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attrs, $classMatch)) {
                $classes = trim((string) $classMatch[1]);
                if (stripos($classes, 'btn-primary') === false) {
                    $classes = trim($classes . ' btn-primary');
                }
                if (stripos($classes, 'btn') === false) {
                    $classes = trim('btn ' . $classes);
                }
                if (stripos($classes, 'itm-list-new-button') === false) {
                    $classes = trim($classes . ' itm-list-new-button');
                }
                $attrs = (string) preg_replace(
                    '/\bclass\s*=\s*["\'][^"\']*["\']/i',
                    'class="' . $classes . '"',
                    $attrs,
                    1
                );
            } else {
                $attrs .= ' class="btn btn-primary itm-list-new-button"';
            }

            if (preg_match('/\btitle\s*=\s*["\'][^"\']*["\']/i', $attrs) === 1) {
                $attrs = (string) preg_replace('/\btitle\s*=\s*["\'][^"\']*["\']/i', 'title="Create"', $attrs, 1);
            } else {
                $attrs .= ' title="Create"';
            }

            return '<a' . $attrs . '>' . $matches[2] . '</a>';
        },
        $anchorHtml,
        1,
        $replaceCount
    );

    if (!is_string($updated) || $replaceCount < 1) {
        return null;
    }

    return $updated;
}

foreach (glob($root . 'modules' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'index.php') ?: [] as $indexPath) {
    $scanned++;
    $content = (string) file_get_contents($indexPath);
    if (!preg_match('/<a\b[^>]*\bhref\s*=\s*["\'][^"\']*create\.php[^"\']*["\'][^>]*>/i', $content)) {
        $skippedNoCreate++;
        continue;
    }

    $check = itm_check_new_button_style($content, true, '');
    if (($check['status'] ?? '') === 'pass'
        && stripos($content, 'itm-list-new-button') !== false
    ) {
        $skippedPass++;
        continue;
    }

    $before = $content;
    $anchors = itm_ui_index_collect_list_new_button_anchors($content);
    foreach ($anchors as $anchorHtml) {
        $normalized = itm_apply_normalize_list_new_button_anchor($anchorHtml);
        if ($normalized === null || $normalized === $anchorHtml) {
            continue;
        }
        $content = str_replace($anchorHtml, $normalized, $content);
    }

    if ($content === $before) {
        continue;
    }

    $relative = str_replace($root, '', $indexPath);
    if ($apply) {
        file_put_contents($indexPath, $content);
    }
    $changed[] = $relative;
}

echo 'Apply list new button style' . $nl;
echo 'Scanned: ' . $scanned . $nl;
echo 'Skipped (already compliant): ' . $skippedPass . $nl;
echo 'Skipped (no create.php link): ' . $skippedNoCreate . $nl;
echo ($apply ? 'Updated' : 'Would update') . ': ' . count($changed) . $nl;
foreach ($changed as $path) {
    echo ($apply ? '[apply] ' : '[dry-run] ') . $path . $nl;
}

if (!$apply && $changed !== []) {
    echo $nl . 'Re-run with --apply (CLI) or ?apply=1 (browser, Admin) to write files.' . $nl;
}
