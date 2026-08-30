<?php
/**
 * Apply itm-plain-link (or sort-header inherit) to decorated module inline anchors.
 *
 * Browser: [apply_module_decorated_plain_links.php?run=1](http://localhost/it-management/scripts/apply_module_decorated_plain_links.php?run=1)
 * Apply: [apply_module_decorated_plain_links.php?run=1&apply=1](http://localhost/it-management/scripts/apply_module_decorated_plain_links.php?run=1&apply=1)
 * CLI: php scripts/apply_module_decorated_plain_links.php [--apply] [--module=slug]
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required for apply.</strong> Dry-run: <code>?run=1</code> · Apply: <code>?run=1&amp;apply=1</code> · filter: <code>?module=problems</code><br>
CLI: <code>php scripts/apply_module_decorated_plain_links.php</code> · <code>--apply</code> · <code>--module=slug</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_module_decorated_links_apply.php';

$boot = itm_apply_script_bootstrap('Apply module decorated plain links');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$moduleSlug = '';
if (PHP_SAPI === 'cli') {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleSlug = substr($arg, 9);
        }
    }
} else {
    $moduleSlug = trim((string)($_GET['module'] ?? ''));
}

$result = itm_module_decorated_links_apply_all($root, $apply, $moduleSlug);
$changed = $result['changed_files'];

$mode = $apply ? 'Updated' : 'Would update';
echo $nl . $mode . ' ' . count($changed) . ' file(s).' . $nl . $nl;
foreach ($changed as $rel => $lines) {
    $lineList = $lines !== [] ? implode(',', $lines) : 'return-string';
    echo ($apply ? '[APPLY]' : '[DRY]') . ' ' . $rel . ' lines=' . $lineList . $nl;
}

if (!$apply) {
    echo $nl . 'Re-run with --apply or ?apply=1 to write.' . $nl;
}

exit(0);
