<?php
/**
 * Insert server-side favicon <link> in module index/create/edit/view/list_all <head> blocks.
 *
 * Why: fields_missing bespoke gate and first-paint tab icon require itm_render_head_favicon_link()
 * using Settings favicon_url (header.php JS alone leaves the default globe).
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_titles_list_audit.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';

$boot = itm_apply_script_bootstrap('Apply head favicon link');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$insertLine = '    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>' . "\n";
$legacySettingsBlock = "    <?php if (\$currentFaviconUrl !== \"\"): ?>\n        <link rel=\"icon\" type=\"image/x-icon\" href=\"<?php echo sanitize(\$currentFaviconUrl); ?>\">\n    <?php endif; ?>\n";

$scanned = 0;
$skippedPass = 0;
$skippedNoHead = 0;
$warned = [];
$changed = [];

$entryFiles = [];
foreach (itm_verify_module_page_chrome_entry_basenames() as $entryName) {
    foreach (glob($root . 'modules' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $entryName) ?: [] as $entryPath) {
        $entryFiles[] = $entryPath;
    }
}

foreach ($entryFiles as $indexPath) {
    $scanned++;
    $content = (string) file_get_contents($indexPath);
    if (stripos($content, '<head') === false) {
        $skippedNoHead++;
        continue;
    }

    $before = $content;
    $check = itm_check_module_favicon_link($content);
    if (($check['status'] ?? '') === 'pass') {
        $skippedPass++;
        continue;
    }

    if (strpos($content, $legacySettingsBlock) !== false) {
        $content = str_replace($legacySettingsBlock, $insertLine, $content);
    }

    if (preg_match('/<link\b[^>]*\brel\s*=\s*["\'](?:shortcut\s+icon|icon)["\'][^>]*>/i', $content) === 1
        && stripos($content, 'itm_render_head_favicon_link') === false
    ) {
        $replaced = preg_replace(
            '/\n\s*<link\b[^>]*\brel\s*=\s*["\'](?:shortcut\s+icon|icon)["\'][^>]*>\s*(?:\r\n|\n|\r)/i',
            "\n" . $insertLine,
            $content,
            1,
            $iconReplaceCount
        );
        if (is_string($replaced) && $iconReplaceCount > 0) {
            $content = $replaced;
        }
    }

    if (stripos($content, 'itm_render_head_favicon_link') !== false) {
        $replaced = preg_replace(
            '/<\?php\s+echo\s+itm_render_head_favicon_link\([^)]*\);\s*\?>/i',
            trim($insertLine),
            $content,
            1,
            $helperReplaceCount
        );
        if (is_string($replaced) && $helperReplaceCount > 0) {
            $content = $replaced;
        }
    }

    if (stripos($content, 'itm_render_head_favicon_link') === false) {
        $replaced = preg_replace_callback(
            '/(<\/title>)/i',
            static function (array $matches) use ($insertLine): string {
                return $matches[1] . "\n" . $insertLine;
            },
            $content,
            1,
            $titleReplaceCount
        );
        if (is_string($replaced) && $titleReplaceCount > 0) {
            $content = $replaced;
        }
    }

    if ($content === $before) {
        continue;
    }

    $afterCheck = itm_check_module_favicon_link($content);
    if (($afterCheck['status'] ?? '') !== 'pass') {
        $warned[] = str_replace($root, '', $indexPath) . ' — ' . ($afterCheck['details'] ?? '');
        continue;
    }

    $changed[] = str_replace($root, '', $indexPath);
    if ($apply) {
        file_put_contents($indexPath, $content);
    }
}

echo 'Scanned: ' . $scanned . $nl;
echo 'Skipped (no <head>): ' . $skippedNoHead . $nl;
echo 'Skipped (already pass): ' . $skippedPass . $nl;
echo ($apply ? 'Changed' : 'Would change') . ': ' . count($changed) . $nl;
foreach ($changed as $relPath) {
    echo '  - ' . $relPath . $nl;
}
if ($warned !== []) {
    echo 'Warnings: ' . count($warned) . $nl;
    foreach ($warned as $warnLine) {
        echo '  [WARN] ' . $warnLine . $nl;
    }
}

if (!$apply && $changed !== []) {
    echo $nl . 'Dry-run only. Re-run with --apply or ?apply=1 to write.' . $nl;
}

itm_script_output_end();
exit($changed === [] ? 0 : ($apply ? 0 : 2));
