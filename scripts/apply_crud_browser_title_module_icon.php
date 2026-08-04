<?php
/**
 * Inject itm_crud_apply_module_icon_to_browser_title() before canonical <title> tags in modules/.
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="apply_crud_browser_title_module_icon.php">dry-run</a> / <a href="apply_crud_browser_title_module_icon.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_browser_title_module_icon.php</code> then <code>php scripts/apply_crud_browser_title_module_icon.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_crud_browser_title_apply.php';

$boot = itm_apply_script_bootstrap('Apply CRUD browser title module icon');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$modulesDir = $root . 'modules';

$scanned = 0;
$patched = [];
$skipped = [];

foreach (itm_crud_browser_title_collect_module_php_files($modulesDir) as $path) {
    $scanned++;
    $relative = str_replace('\\', '/', substr($path, strlen($root)));
    $content = (string) file_get_contents($path);
    $result = itm_crud_browser_title_apply_to_content($content);
    if (!($result['changed'] ?? false)) {
        $skipped[] = $relative . ' (' . ($result['reason'] ?? 'skip') . ')';
        continue;
    }
    $newContent = (string) ($result['content'] ?? '');
    if ($newContent === '') {
        $skipped[] = $relative . ' (empty_patch)';
        continue;
    }
    if ($apply) {
        file_put_contents($path, $newContent);
    }
    $patched[] = $relative;
}

echo 'Scanned: ' . $scanned . $nl;
echo 'Patched: ' . count($patched) . ($apply ? '' : ' (dry-run)') . $nl;
foreach ($patched as $line) {
    echo '  ' . $line . $nl;
}
if ($skipped !== []) {
    echo 'Skipped: ' . count($skipped) . $nl;
}

itm_script_output_end();
exit(0);
