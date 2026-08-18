<?php
/**
 * Static audit: list modules whose index table may show a Company column (company_id).
 *
 * Scans every PHP file under modules/{slug}/** (recursive), not index.php alone.
 *
 * Browser: scripts/check_company_id_ui_column.php (Administrator session).
 * CLI: php scripts/check_company_id_ui_column.php [--list-exposed] [--strict]
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_company_id_ui_column_audit.php';

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text inventory with per-module links to <code>modules/{slug}/index.php</code> (new tab). CLI: <code>php scripts/check_company_id_ui_column.php</code> — static scan of every <code>modules/{slug}/**/*.php</code> file plus <code>db/01_schema.sql</code> (no database). Default exit <code>0</code> (report only). <code>--list-exposed</code> prints exposed module slugs only. <code>--strict</code> exits <code>1</code> when any scaffold module table is missing from <code>$hideCompanyIdTables</code> on one or more scaffold entry files.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$nl = itm_check_script_begin_browser_admin('Company column UI audit');

$argvList = $GLOBALS['argv'] ?? [];
$listExposed = in_array('--list-exposed', $argvList, true)
    || (isset($_GET['list_exposed']) && (string) $_GET['list_exposed'] === '1');
$strict = in_array('--strict', $argvList, true)
    || (isset($_GET['strict']) && (string) $_GET['strict'] === '1');

$report = itm_company_id_ui_column_collect_report(dirname(__DIR__));
$linkModules = !itm_script_access_is_cli();

if ($listExposed) {
    $exposed = array_keys($report['scaffold_exposed'] + $report['bespoke_exposed']);
    sort($exposed);
    foreach ($exposed as $slug) {
        if ($linkModules) {
            require_once __DIR__ . '/lib/script_browser_nav.php';
            echo itm_script_format_modules_file_local_dev_link(
                'modules/' . $slug . '/index.php',
                $slug
            ) . $nl;
            continue;
        }
        echo $slug . $nl;
    }
    itm_script_output_end();
    exit(0);
}

echo itm_company_id_ui_column_format_report($report, $nl, $linkModules);

if ($strict && $report['scaffold_exposed'] !== []) {
    echo 'FAIL: ' . count($report['scaffold_exposed']) . ' scaffold module(s) expose company_id on list UI.' . $nl;
    itm_script_output_end();
    exit(1);
}

echo 'PASS: report complete.' . ($strict ? ' No scaffold modules expose company_id.' : '') . $nl;
itm_script_output_end();
exit(0);
