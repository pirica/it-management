<?php
/**
 * Remove duplicated inline bulk-delete selection scripts after bulk-delete-selection.js loads globally.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Bulk Delete Cancel UX Fix');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

// Why: do not match across </script> — some pages keep import/color helpers in an earlier script tag.
$pattern = '/<script>\s*(?:\/\*\*[\s\S]*?\*\/\s*)?\(function\s*\(\)\s*\{(?:(?!<\/script>)[\s\S])*?let\s+selectionMode\s*=\s*false;(?:(?!<\/script>)[\s\S])*?\}\)\(\);\s*<\/script>\s*/i';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/modules', RecursiveDirectoryIterator::SKIP_DOTS)
);
$changed = [];
$skipped = [];

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }
    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    if (!is_string($content) || stripos($content, 'let selectionMode = false') === false) {
        continue;
    }
    if (preg_match($pattern, $content) !== 1) {
        $skipped[] = str_replace('\\', '/', substr($path, strlen($root) + 1)) . ' (pattern mismatch)';
        continue;
    }
    $next = preg_replace($pattern, '', $content, 1);
    if (!is_string($next) || $next === $content) {
        $skipped[] = str_replace('\\', '/', substr($path, strlen($root) + 1)) . ' (no change)';
        continue;
    }
    if ($apply) {
        file_put_contents($path, $next);
    }
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $changed[] = $rel;
    echo ($apply ? '[apply] ' : '[dry-run] ') . "updated: {$rel}" . $nl;
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' file(s); skipped ' . count($skipped) . '.' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('Skipped', $skipped);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_bulk_delete_cancel_ux.php');

itm_script_output_end();
exit(0);
