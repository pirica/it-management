<?php
/**
 * Static gate: browser-capable catalog *.php scripts define usage + entry hook.
 *
 * Browser: scripts/check_script_browser_usage.php (Administrator)
 * CLI: php scripts/check_script_browser_usage.php
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_script_catalog_tags.php';
require_once __DIR__ . '/lib/itm_script_catalog_usage_migrate.php';
require_once __DIR__ . '/lib/itm_script_browser_usage.php';

$nl = itm_check_script_begin_browser_admin('Script browser usage coverage');
$root = dirname(__DIR__);
$catalogPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'scripts.php';
$html = file_get_contents($catalogPath);
if (!is_string($html)) {
    echo '[FAIL] could not read scripts/scripts.php' . $nl;
    itm_script_output_end();
    exit(1);
}

$rows = itm_script_catalog_tags_parse_catalog_rows($html);
$exempt = itm_script_browser_usage_exempt_basenames();
$failures = [];
$checked = 0;

foreach ($rows as $row) {
    $slug = $row['slug'];
    if (!itm_script_catalog_usage_row_is_php_href($slug, $row['href'])) {
        continue;
    }
    if (preg_match('#^https?://#i', trim($row['href']))) {
        continue;
    }
    if (!itm_script_catalog_usage_row_is_browser_capable($row['row_html'])) {
        continue;
    }
    if (in_array($slug, $exempt, true)) {
        continue;
    }

    $checked++;
    $path = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $slug;
    if (!is_file($path)) {
        $failures[] = $slug . ': missing file';
        continue;
    }

    $src = file_get_contents($path);
    if (!is_string($src)) {
        $failures[] = $slug . ': unreadable';
        continue;
    }

    if (strpos($src, 'function itm_script_browser_how_to_use') === false) {
        $failures[] = $slug . ': missing itm_script_browser_how_to_use()';
        continue;
    }

    $hasHook = strpos($src, 'itm_script_browser_usage_maybe_gate') !== false
        || strpos($src, 'itm_apply_script_bootstrap') !== false
        || strpos($src, 'itm_script_regression_entry.php') !== false
        || strpos($src, 'itm_check_script_begin_browser_admin') !== false
        || strpos($src, 'itm_script_output_begin') !== false;

    if (!$hasHook) {
        $failures[] = $slug . ': missing browser usage gate hook';
    }

    if (strpos($row['row_html'], 'scripts-catalog-how-stub') === false) {
        $failures[] = $slug . ': catalog row missing scripts-catalog-how-stub';
    }
}

echo '[INFO] Browser-capable PHP catalog scripts checked: ' . $checked . $nl;

if ($failures === []) {
    echo colorText('[PASS] Script browser usage coverage OK', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] ' . count($failures) . ' issue(s)', 'fail') . $nl;
foreach ($failures as $line) {
    echo '  - ' . $line . $nl;
}
itm_script_output_end();
exit(1);
