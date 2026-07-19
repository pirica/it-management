<?php
/**
 * Cross-check module page chrome: browser <title> (titles_list.php) + Settings favicon wiring.
 *
 * Why: fields_missing bespoke gate audits index/create/edit/view per module; titles_list.php
 * scans every modules PHP file under modules/. This script reports both in one place for debug/verify.
 *
 * Browser + CLI (Admin). Exit 1 when any scanned <head> file fails title or favicon contract.
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_titles_list_audit.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';

itm_script_output_begin('Verify module page chrome');
$nl = itm_script_output_nl();
$root = dirname(__DIR__);
$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';

if (!is_dir($modulesDir)) {
    echo 'FAIL: modules/ directory does not exist.' . $nl;
    itm_script_output_end();
    exit(1);
}

$files = itm_titles_list_collect_module_php_files($modulesDir);
$stats = [
    'scanned' => count($files),
    'with_head' => 0,
    'title_pass' => 0,
    'title_fail' => 0,
    'favicon_pass' => 0,
    'favicon_fail' => 0,
    'title_na' => 0,
    'favicon_na' => 0,
];
$failures = [];

foreach ($files as $path) {
    $content = (string) file_get_contents($path);
    if (!itm_fields_missing_file_has_standalone_html_head($content)) {
        continue;
    }

    $stats['with_head']++;
    $modulePath = itm_titles_list_module_path_from_root($root, $path);

    $titleBlock = itm_ui_extract_title_block($content);
    if ($titleBlock === '') {
        $stats['title_na']++;
        $failures[] = $modulePath . ' — missing <title>';
    } elseif (itm_titles_list_title_matches_canonical($titleBlock)
        || (itm_check_module_browser_title($content)['status'] ?? '') === 'pass'
    ) {
        $stats['title_pass']++;
    } else {
        $stats['title_fail']++;
        $failures[] = $modulePath . ' — browser title not canonical (see titles_list.php)';
    }

    $faviconCheck = itm_check_module_favicon_link($content, $content);
    $faviconStatus = strtolower((string) ($faviconCheck['status'] ?? ''));
    if ($faviconStatus === 'n/a') {
        $stats['favicon_na']++;
    } elseif ($faviconStatus === 'pass') {
        $stats['favicon_pass']++;
    } else {
        $stats['favicon_fail']++;
        $failures[] = $modulePath . ' — favicon: ' . (string) ($faviconCheck['details'] ?? 'contract failed');
    }
}

echo '--- Module page chrome verify ---' . $nl;
echo 'PHP files scanned: ' . (int) $stats['scanned'] . $nl;
echo 'With standalone <head>: ' . (int) $stats['with_head'] . $nl;
echo 'Browser title pass: ' . (int) $stats['title_pass'] . $nl;
echo 'Browser title fail: ' . (int) $stats['title_fail'] . $nl;
echo 'Favicon pass: ' . (int) $stats['favicon_pass'] . $nl;
echo 'Favicon fail: ' . (int) $stats['favicon_fail'] . $nl;
echo 'Canonical title pattern: ' . itm_titles_list_expected_title_literal() . $nl;
echo 'Related: titles_list.php, titles_list_show.php, fields_missing.php, crud_titles.php' . $nl;
echo '-------------------------------' . $nl . $nl;

if ($failures !== []) {
    echo 'Failures (' . count($failures) . '):' . $nl;
    foreach ($failures as $line) {
        echo '  [FAIL] ' . $line . $nl;
    }
    echo $nl;
}

$exitCode = ($stats['title_fail'] > 0 || $stats['favicon_fail'] > 0) ? 1 : 0;
echo $exitCode === 0 ? 'Result: pass' : 'Result: fail';
echo $nl;

itm_script_output_end();
exit($exitCode);
