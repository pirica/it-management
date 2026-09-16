<?php
/**
 * Move scripts.php "How to use" text into each cataloged *.php script; stub catalog cells.
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 *
 * Usage:
 *   php scripts/apply_script_catalog_usage_to_php.php
 *   php scripts/apply_script_catalog_usage_to_php.php --apply
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_script_catalog_tags.php';
require_once __DIR__ . '/lib/itm_script_catalog_usage_migrate.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: open this script (dry-run default). CLI: php scripts/apply_script_catalog_usage_to_php.php then --apply (Admin in browser for apply=1).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Apply script catalog usage to PHP', ['supports_apply' => true]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');
$scriptsRoot = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR;

$catalogPath = $scriptsRoot . 'scripts.php';
$catalogHtml = file_get_contents($catalogPath);
if (!is_string($catalogHtml)) {
    echo 'FAIL: could not read scripts/scripts.php' . $nl;
    itm_script_output_end();
    exit(1);
}

$rows = itm_script_catalog_tags_parse_catalog_rows($catalogHtml);
$patchedCatalog = $catalogHtml;
$injected = [];
$gateWired = [];
$skipped = [];
$stubbed = [];

foreach ($rows as $row) {
    $slug = $row['slug'];
    $href = $row['href'];
    if (!itm_script_catalog_usage_row_is_php_href($slug, $href)) {
        continue;
    }

    $howHtml = itm_script_catalog_usage_extract_fifth_td($row['row_html']);
    $usageBody = itm_script_catalog_usage_html_to_usage_body($howHtml);
    $scriptPath = $scriptsRoot . $slug;

    if (is_file($scriptPath)) {
        $phpSource = file_get_contents($scriptPath);
        if (is_string($phpSource)) {
            $inject = itm_script_catalog_usage_inject_into_php($phpSource, $usageBody);
            if (!empty($inject['changed']) && isset($inject['source'])) {
                $injected[] = $slug;
                $phpSource = $inject['source'];
                if ($apply) {
                    file_put_contents($scriptPath, $phpSource);
                }
            } else {
                if (($inject['reason'] ?? '') !== 'already_has_usage_function') {
                    $skipped[] = $slug . ' (' . ($inject['reason'] ?? 'skip') . ')';
                }
            }

            if ($phpSource !== '') {
                $gate = itm_script_catalog_usage_inject_gate_after_config($phpSource);
                if (!empty($gate['changed']) && isset($gate['source'])) {
                    $phpSource = $gate['source'];
                    $gateWired[] = $slug . ' (after config)';
                } else {
                    $gate = itm_script_catalog_usage_inject_gate_before_output_begin($phpSource);
                    if (!empty($gate['changed']) && isset($gate['source'])) {
                        $phpSource = $gate['source'];
                        $gateWired[] = $slug . ' (before output_begin)';
                    }
                }
                if ($apply && $phpSource !== file_get_contents($scriptPath)) {
                    file_put_contents($scriptPath, $phpSource);
                }
            }
        }
    } else {
        $skipped[] = $slug . ' (missing_file)';
    }

    if (strpos($row['row_html'], 'scripts-catalog-how-stub') !== false) {
        continue;
    }

    $newInner = itm_script_catalog_usage_patch_row_how_cell($row['row_html']);
    if ($newInner !== $row['row_html']) {
        $stubbed[] = $slug;
        $oldRow = '<tr' . $row['attrs'] . '>' . $row['row_html'] . '</tr>';
        $newRow = '<tr' . $row['attrs'] . '>' . $newInner . '</tr>';
        $patchedCatalog = str_replace($oldRow, $newRow, $patchedCatalog);
    }
}

echo 'PHP scripts with usage injected: ' . count($injected) . $nl;
foreach ($injected as $slug) {
    echo '  + ' . $slug . $nl;
}
echo 'Catalog how-cells stubbed: ' . count($stubbed) . $nl;
echo 'Explicit browser gates wired: ' . count($gateWired) . $nl;
if ($skipped !== []) {
    echo 'Skipped (' . count($skipped) . '):' . $nl;
    foreach ($skipped as $line) {
        echo '  - ' . $line . $nl;
    }
}

if ($apply) {
    if ($patchedCatalog !== $catalogHtml) {
        file_put_contents($catalogPath, $patchedCatalog);
        echo $nl . 'Wrote scripts/scripts.php catalog stubs.' . $nl;
    }
    echo $nl . 'Apply complete.' . $nl;
} else {
    echo $nl . 'Dry-run — re-run with --apply or ?apply=1 (Admin) to write files.' . $nl;
}

itm_script_output_end();
