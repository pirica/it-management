<?php
/**
 * Regression: CAPEX/OPEX report modules and budget_categories.category_kind.
 *
 * CLI: php scripts/verify_capex_opex.php
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_capex_opex.php</code>. Run after changing <code>includes/itm_budget_category_report.php</code>, <code>modules/capex/</code>, <code>modules/opex/</code>, or <code>budget_categories.category_kind</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_budget_category_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('CAPEX / OPEX report verification');

$nl = itm_script_output_nl();
$failures = 0;

function vco_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vco_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    vco_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$backfilledKinds = itm_budget_category_report_backfill_category_kinds($conn);
if ($backfilledKinds > 0) {
    vco_pass('Backfilled ' . $backfilledKinds . ' budget_categories.category_kind row(s) from canonical names.');
}

$companyId = 1;
$reportYear = itm_budget_category_report_default_year($conn, $companyId);
$seededCapital = itm_budget_category_report_ensure_capital_annual_budget_rows($conn, $reportYear);
if ($seededCapital > 0) {
    vco_pass('Inserted ' . $seededCapital . ' GL 7100 annual budget row(s) for year ' . $reportYear . '.');
}
$columnRes = mysqli_query($conn, "SHOW COLUMNS FROM budget_categories LIKE 'category_kind'");
if (!$columnRes || mysqli_num_rows($columnRes) === 0) {
    vco_fail('budget_categories.category_kind column missing — import db/ or run migration budget_categories_category_kind.sql.');
} else {
    vco_pass('budget_categories.category_kind column exists.');
}

$seedChecks = [
    'Capital Expense' => 'capex',
    'Operating Expense' => 'opex',
    'Revenue' => 'revenue',
];
foreach ($seedChecks as $name => $expectedKind) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT category_kind FROM budget_categories WHERE company_id = ? AND name = ? LIMIT 1'
    );
    if (!$stmt) {
        vco_fail('Prepare failed for seed category ' . $name);
        continue;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row || (string)$row['category_kind'] !== $expectedKind) {
        vco_fail('Seed category ' . $name . ' expected category_kind=' . $expectedKind . '.');
    } else {
        vco_pass('Seed category ' . $name . ' maps to category_kind=' . $expectedKind . '.');
    }
}

$year = $reportYear;
$capexResult = itm_budget_category_report_run($conn, [
    'company_id' => $companyId,
    'year' => $year,
    'month' => 0,
    'cost_center_id' => 0,
    'gl_account_id' => 0,
    'search' => '',
    'sort' => 'account_code',
    'dir' => 'ASC',
    'category_kind' => 'capex',
]);
if ($capexResult['error'] !== '') {
    vco_fail('CAPEX report error: ' . $capexResult['error']);
} else {
    vco_pass('CAPEX report SQL executed (full-year mode).');
}

$opexResult = itm_budget_category_report_run($conn, [
    'company_id' => $companyId,
    'year' => $year,
    'month' => 0,
    'cost_center_id' => 0,
    'gl_account_id' => 0,
    'search' => '',
    'sort' => 'account_code',
    'dir' => 'ASC',
    'category_kind' => 'opex',
]);
if ($opexResult['error'] !== '') {
    vco_fail('OPEX report error: ' . $opexResult['error']);
} else {
    vco_pass('OPEX report SQL executed (full-year mode).');
}

$capexCodes = [];
foreach ($capexResult['rows'] as $row) {
    $capexCodes[] = (string)$row['account_code'];
}
$opexCodes = [];
foreach ($opexResult['rows'] as $row) {
    $opexCodes[] = (string)$row['account_code'];
}

if (in_array('7100', $capexCodes, true)) {
    vco_pass('CAPEX report includes GL 7100 (Capital IT Equipment).');
} else {
    vco_fail('CAPEX report missing GL 7100 when seed data is present.');
}

if (in_array('6100', $opexCodes, true) || in_array('6200', $opexCodes, true)) {
    vco_pass('OPEX report includes operating GL account(s) from seed data.');
} else {
    vco_fail('OPEX report missing 6100/6200 when seed data is present.');
}

if (in_array('7100', $opexCodes, true)) {
    vco_fail('OPEX report incorrectly includes capital GL 7100.');
} else {
    vco_pass('OPEX report excludes capital GL 7100.');
}

if (in_array('6100', $capexCodes, true) || in_array('6200', $capexCodes, true)) {
    vco_fail('CAPEX report incorrectly includes operating GL 6100/6200.');
} else {
    vco_pass('CAPEX report excludes operating GL 6100/6200.');
}

$seedCompanyIds = itm_budget_category_report_seed_company_ids($conn);
if (count($seedCompanyIds) < 5) {
    vco_fail('Expected five seed companies (id 1–5) for multi-tenant CAPEX/OPEX demo checks.');
} else {
    vco_pass('Seed company list includes five tenants.');
}

foreach ($seedCompanyIds as $seedCompanyId) {
    $companyYear = itm_budget_category_report_default_year($conn, $seedCompanyId);
    $companyCapex = itm_budget_category_report_run($conn, [
        'company_id' => $seedCompanyId,
        'year' => $companyYear,
        'month' => 0,
        'cost_center_id' => 0,
        'gl_account_id' => 0,
        'search' => '',
        'sort' => 'account_code',
        'dir' => 'ASC',
        'category_kind' => 'capex',
    ]);
    $companyOpex = itm_budget_category_report_run($conn, [
        'company_id' => $seedCompanyId,
        'year' => $companyYear,
        'month' => 0,
        'cost_center_id' => 0,
        'gl_account_id' => 0,
        'search' => '',
        'sort' => 'account_code',
        'dir' => 'ASC',
        'category_kind' => 'opex',
    ]);
    if ($companyCapex['error'] !== '') {
        vco_fail('Company ' . $seedCompanyId . ' CAPEX error: ' . $companyCapex['error']);
        continue;
    }
    if ($companyOpex['error'] !== '') {
        vco_fail('Company ' . $seedCompanyId . ' OPEX error: ' . $companyOpex['error']);
        continue;
    }

    $companyCapexCodes = [];
    foreach ($companyCapex['rows'] as $row) {
        $companyCapexCodes[] = (string)$row['account_code'];
    }
    $companyOpexCodes = [];
    foreach ($companyOpex['rows'] as $row) {
        $companyOpexCodes[] = (string)$row['account_code'];
    }

    if (!in_array('7100', $companyCapexCodes, true)) {
        vco_fail('Company ' . $seedCompanyId . ' CAPEX missing GL 7100 for year ' . $companyYear . '.');
    } else {
        vco_pass('Company ' . $seedCompanyId . ' CAPEX includes GL 7100 (year ' . $companyYear . ').');
    }

    if (!in_array('6100', $companyOpexCodes, true) && !in_array('6200', $companyOpexCodes, true)) {
        vco_fail('Company ' . $seedCompanyId . ' OPEX missing GL 6100/6200 for year ' . $companyYear . '.');
    } else {
        vco_pass('Company ' . $seedCompanyId . ' OPEX includes operating GL account(s) (year ' . $companyYear . ').');
    }

    if (in_array('7100', $companyOpexCodes, true)) {
        vco_fail('Company ' . $seedCompanyId . ' OPEX incorrectly includes GL 7100.');
    }
}

$monthResult = itm_budget_category_report_run($conn, [
    'company_id' => $companyId,
    'year' => $year,
    'month' => 1,
    'cost_center_id' => 0,
    'gl_account_id' => 0,
    'search' => '',
    'sort' => 'cost_center',
    'dir' => 'ASC',
    'category_kind' => 'capex',
]);
if ($monthResult['error'] !== '') {
    vco_fail('CAPEX month-mode report error: ' . $monthResult['error']);
} elseif (!$monthResult['is_month_mode']) {
    vco_fail('CAPEX month-mode flag not set when month=1.');
} else {
    vco_pass('CAPEX month-mode report executed.');
}

if (!is_file(ROOT_PATH . 'modules/capex/index.php') || !is_file(ROOT_PATH . 'modules/opex/index.php')) {
    vco_fail('modules/capex/index.php or modules/opex/index.php missing.');
} else {
    vco_pass('CAPEX and OPEX module entry files exist.');
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
