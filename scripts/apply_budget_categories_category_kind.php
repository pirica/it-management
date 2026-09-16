<?php
/**
 * Backfill budget_categories.category_kind and optional GL 7100 annual budgets for CAPEX reports.
 *
 * CLI: php scripts/apply_budget_categories_category_kind.php [--apply]
 * Browser: scripts/apply_budget_categories_category_kind.php?run=1&apply=1 (Admin)
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/apply_budget_categories_category_kind.php --apply</code> (session company) or <code>--apply --all-companies</code> (seed companies 1–5). Maps canonical category names to <code>category_kind</code> and inserts CAPEX/OPEX demo budgets (GL 6100/6200/7100), January monthly splits, and one Posted 6100 expense per company.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_budget_category_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Apply budget_categories.category_kind backfill');

$nl = itm_script_output_nl();
$apply = false;
$allCompanies = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if ($arg === '--apply') {
            $apply = true;
        }
        if ($arg === '--all-companies') {
            $allCompanies = true;
        }
    }
} else {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Apply category_kind</title></head><body><pre>';
    $apply = !empty($_REQUEST['apply']);
    $allCompanies = !empty($_REQUEST['all_companies']);
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if (!itm_budget_category_report_category_kind_column_exists($conn)) {
    echo colorText('[FAIL] budget_categories.category_kind missing — import db/ or run migration budget_categories_category_kind.sql.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if (!$apply) {
    echo colorText('[INFO] Dry-run — pass --apply or ?run=1&apply=1 to write.', 'info') . $nl;
    itm_script_output_end();
    exit(0);
}

$companyId = (int)($_SESSION['company_id'] ?? 1);
$kindUpdates = itm_budget_category_report_backfill_category_kinds($conn);
echo colorText('[PASS] Updated ' . $kindUpdates . ' budget_categories.category_kind row(s).', 'pass') . $nl;

$year = itm_budget_category_report_default_year($conn, $companyId);
$seedCompanyId = $allCompanies ? null : $companyId;
$seedStats = itm_budget_category_report_ensure_demo_sample_rows($conn, $year, $seedCompanyId);
$scopeLabel = $allCompanies ? 'seed companies 1–5' : 'company ' . $companyId;
echo colorText(
    '[PASS] Demo sample rows for ' . $scopeLabel . ' (year ' . $year . '): '
    . (int)$seedStats['annual'] . ' annual, '
    . (int)$seedStats['monthly'] . ' monthly, '
    . (int)$seedStats['expenses'] . ' expense(s), '
    . (int)$seedStats['companies'] . ' company pass(es).',
    'pass'
) . $nl;

itm_script_output_end();
exit(0);
