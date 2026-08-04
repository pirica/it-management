<?php
/**
 * Static gate: canonical <title> blocks must call itm_crud_apply_module_icon_to_browser_title().
 *
 * Browser + CLI (Admin). Exit 1 on missing helper injection.
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="check_crud_browser_title_module_icon.php">check_crud_browser_title_module_icon.php</a>. CLI: <code>php scripts/check_crud_browser_title_module_icon.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_crud_browser_title_apply.php';

itm_script_output_begin('Check CRUD browser title module icon');
$nl = itm_script_output_nl();
$root = dirname(__DIR__);
$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$failures = [];
$ok = 0;

foreach (itm_crud_browser_title_collect_module_php_files($modulesDir) as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $content = (string) file_get_contents($path);
    if (!itm_crud_browser_title_file_has_canonical_title($content)) {
        continue;
    }
    if (strpos($content, itm_crud_browser_title_helper_marker()) === false) {
        $failures[] = $relative;
        continue;
    }
    $ok++;
}

echo 'Canonical title with helper: ' . $ok . $nl;
echo 'Missing helper: ' . count($failures) . $nl;
foreach ($failures as $file) {
    echo colorText('[FAIL]', 'fail') . ' ' . $file . $nl;
    if (function_exists('itm_script_modules_repo_path_to_local_url')) {
        echo '      Open in browser (new tab): ' . itm_script_modules_repo_path_to_local_url($file) . $nl;
    }
}

$exitCode = $failures === [] ? 0 : 1;
echo $exitCode === 0
    ? colorText('[PASS] Result: pass', 'pass')
    : colorText('[FAIL] Result: fail', 'fail');
echo $nl;

itm_script_output_end();
exit($exitCode);
