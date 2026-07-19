<?php
/**
 * Cross-check module page chrome: browser <title> (titles_list.php) + Settings favicon wiring.
 *
 * Why: fields_missing bespoke gate audits index/create/edit/view/list_all per module;
 * titles_list.php scans every modules PHP file under modules/. This script reports title +
 * favicon for that scope; auxiliary files (delete, export, partials, join) are [SKIP] not [FAIL].
 *
 * Browser + CLI (Admin). Exit 1 when any in-scope scanned <head> file fails title or favicon.
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_titles_list_audit.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';

if (!function_exists('itm_verify_page_chrome_format_entry_line')) {
    /**
     * @param 'FAIL'|'SKIP' $status
     */
    function itm_verify_page_chrome_format_entry_line(string $status, string $modulePath, string $detail): string
    {
        $prefix = $status === 'FAIL'
            ? colorText('[FAIL]', 'fail')
            : colorText('[SKIP]', 'warn');

        return '  ' . $prefix . ' '
            . itm_script_format_modules_file_link($modulePath)
            . ' — '
            . itm_script_escape_browser_pre_text($detail);
    }
}

itm_script_output_begin('Verify module page chrome');
$nl = itm_script_output_nl();
$root = dirname(__DIR__);
$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';

if (!is_dir($modulesDir)) {
    echo colorText('[FAIL] modules/ directory does not exist.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$files = itm_titles_list_collect_module_php_files($modulesDir);
$stats = [
    'scanned' => count($files),
    'skipped_out_of_scope' => 0,
    'skipped_no_standalone_head' => 0,
    'with_head' => 0,
    'title_pass' => 0,
    'title_fail' => 0,
    'favicon_pass' => 0,
    'favicon_fail' => 0,
    'title_na' => 0,
    'favicon_na' => 0,
];
/** @var list<array{path:string,detail:string}> $failures */
$failures = [];
/** @var list<array{path:string,detail:string}> $skips */
$skips = [];

foreach ($files as $path) {
    $modulePath = itm_titles_list_module_path_from_root($root, $path);
    $skipReason = itm_verify_module_page_chrome_skip_reason($modulePath);
    if ($skipReason !== null) {
        $stats['skipped_out_of_scope']++;
        $skips[] = ['path' => $modulePath, 'detail' => $skipReason];
        continue;
    }

    $content = (string) file_get_contents($path);
    if (!itm_fields_missing_file_has_standalone_html_head($content)) {
        $stats['skipped_no_standalone_head']++;
        continue;
    }

    $stats['with_head']++;

    $titleBlock = itm_ui_extract_title_block($content);
    if ($titleBlock === '') {
        $stats['title_na']++;
        $failures[] = ['path' => $modulePath, 'detail' => 'missing <title>'];
    } elseif (itm_titles_list_title_matches_canonical($titleBlock)
        || (itm_check_module_browser_title($content)['status'] ?? '') === 'pass'
    ) {
        $stats['title_pass']++;
    } else {
        $stats['title_fail']++;
        $failures[] = [
            'path' => $modulePath,
            'detail' => 'browser title not canonical (see titles_list.php)',
        ];
    }

    $faviconCheck = itm_check_module_favicon_link($content, $content);
    $faviconStatus = strtolower((string) ($faviconCheck['status'] ?? ''));
    if ($faviconStatus === 'n/a') {
        $stats['favicon_na']++;
    } elseif ($faviconStatus === 'pass') {
        $stats['favicon_pass']++;
    } else {
        $stats['favicon_fail']++;
        $failures[] = [
            'path' => $modulePath,
            'detail' => 'favicon: ' . (string) ($faviconCheck['details'] ?? 'contract failed'),
        ];
    }
}

echo '--- Module page chrome verify ---' . $nl;
echo 'PHP files scanned: ' . (int) $stats['scanned'] . $nl;
echo 'Skipped (out of scope — not index/create/edit/view/list_all): ' . (int) $stats['skipped_out_of_scope'] . $nl;
echo 'Skipped (no standalone <head> on CRUD entry): ' . (int) $stats['skipped_no_standalone_head'] . $nl;
echo 'In-scope with standalone <head>: ' . (int) $stats['with_head'] . $nl;
echo 'Browser title pass: ' . (int) $stats['title_pass'] . $nl;
echo 'Browser title fail: ' . (int) $stats['title_fail'] . $nl;
echo 'Favicon pass: ' . (int) $stats['favicon_pass'] . $nl;
echo 'Favicon fail: ' . (int) $stats['favicon_fail'] . $nl;
echo 'Canonical title pattern: ' . itm_script_escape_browser_pre_text(itm_titles_list_expected_title_literal()) . $nl;
echo colorText('[INFO] Related: titles_list.php, titles_list_show.php, fields_missing.php, crud_titles.php', 'info') . $nl;
echo '-------------------------------' . $nl . $nl;

if ($skips !== []) {
    echo 'Skipped (' . count($skips) . '):' . $nl;
    foreach ($skips as $row) {
        echo itm_verify_page_chrome_format_entry_line('SKIP', $row['path'], $row['detail']) . $nl;
    }
    echo $nl;
}

if ($failures !== []) {
    echo 'Failures (' . count($failures) . '):' . $nl;
    foreach ($failures as $row) {
        echo itm_verify_page_chrome_format_entry_line('FAIL', $row['path'], $row['detail']) . $nl;
    }
    echo $nl;
}

$exitCode = ($stats['title_fail'] > 0 || $stats['favicon_fail'] > 0) ? 1 : 0;
echo $exitCode === 0
    ? colorText('[PASS] Result: pass', 'pass')
    : colorText('[FAIL] Result: fail', 'fail');
echo $nl;

itm_script_output_end();
exit($exitCode);
