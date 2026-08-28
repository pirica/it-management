<?php
/**
 * Reports Hub regression checks — helper payloads and chart seed coverage.
 *
 * Browser: scripts/verify_reports_hub.php
 * CLI: php scripts/verify_reports_hub.php
 *
 * Optional: ITM_TEST_COMPANY_ID (default 1)
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_reports_hub.php">verify_reports_hub.php</a>. CLI: <code>php scripts/verify_reports_hub.php</code>. Optional <code>ITM_TEST_COMPANY_ID</code> (default 1). Run when changing <code>modules/reports/</code>, helpers, or Reports Hub seeds in <code>db/</code> split bundle.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'modules/reports/api/helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Reports Hub Verification');

$nl = itm_script_output_nl();
$failures = 0;

/**
 * @param string $message
 * @return void
 */
function rh_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

/**
 * @param string $message
 * @return void
 */
function rh_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * @param int $code
 * @return never
 */
function rh_verify_exit($code)
{
    global $nl, $failures;
    if ($code !== 0) {
        echo $nl . colorText('Reports Hub verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    } else {
        echo $nl . colorText('All Reports Hub checks passed.', 'pass') . $nl;
    }
    itm_script_output_end();
    exit($code);
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    rh_verify_fail('No database connection.');
    rh_verify_exit(1);
}

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
if ($companyId <= 0) {
    rh_verify_fail('ITM_TEST_COMPANY_ID must be a positive integer.');
    rh_verify_exit(1);
}

$company_id = $companyId;

$registryStmt = mysqli_prepare(
    $conn,
    "SELECT module_slug FROM modules_registry WHERE module_slug = 'reports' AND active = 1 LIMIT 1"
);
if (!$registryStmt) {
    rh_verify_fail('Unable to prepare modules_registry lookup.');
    rh_verify_exit(1);
}
mysqli_stmt_execute($registryStmt);
$registryRes = mysqli_stmt_get_result($registryStmt);
$registryRow = $registryRes ? mysqli_fetch_assoc($registryRes) : null;
mysqli_stmt_close($registryStmt);

if (!$registryRow) {
    rh_verify_fail("modules_registry row missing for slug 'reports'.");
} else {
    rh_verify_pass("modules_registry contains active 'reports' slug.");
}

$helperNames = [
    'get_equipment_statistics',
    'get_ticket_statistics',
    'get_hr_statistics',
    'get_network_device_counts',
    'get_budget_statistics',
    'get_floorplan_location_data',
    'get_inventory_stock_levels',
    'get_license_statistics',
    'get_budget_by_department',
    'get_budget_vs_actual_trend',
    'get_budget_yoy_comparison',
    'get_capex_opex_annual_budget_split',
    'get_capex_opex_budget_vs_actual',
    'get_capex_opex_monthly_actual_trend',
    'get_asset_financial_value',
    'get_upcoming_maintenance_forecast',
    'get_employee_growth_trend',
    'get_monthly_actual_comparison',
    'get_equipment_status_statistics',
    'get_monthly_asset_additions',
    'get_assets_by_department',
    'get_ops_summary_metrics',
    'get_ops_occupancy_30day',
    'get_ops_monthly_revenue_yoy',
    'get_ops_revenue_mix_mtd',
    'get_ops_fb_outlet_covers',
];

foreach ($helperNames as $helperName) {
    if (!function_exists($helperName)) {
        rh_verify_fail('Missing helper: ' . $helperName);
        continue;
    }

    try {
        $payload = $helperName();
    } catch (Throwable $e) {
        rh_verify_fail($helperName . ' threw: ' . $e->getMessage());
        continue;
    }

    if (!is_array($payload)) {
        rh_verify_fail($helperName . ' must return an array.');
        continue;
    }

    rh_verify_pass($helperName . '() returned array payload.');
}

$opsSummary = get_ops_summary_metrics();
if ((float)($opsSummary['total_revenue'] ?? 0) <= 0) {
    rh_verify_fail(
        'Hotel Operations MTD total_revenue is zero — run bash scripts/import_database_split.sh or bash scripts/import_database_split.sh Reports Hub ops_report seeds, or add ops_report rows for the current month.'
    );
} else {
    rh_verify_pass('Hotel Operations MTD total_revenue > 0.');
}

$occupancy = get_ops_occupancy_30day();
if (count($occupancy['labels'] ?? []) < 1) {
    rh_verify_fail('30-day occupancy trend has no data points for company ' . $companyId . '.');
} else {
    rh_verify_pass('30-day occupancy trend has ' . count($occupancy['labels']) . ' point(s).');
}

$fbCovers = get_ops_fb_outlet_covers();
if (count($fbCovers['labels'] ?? []) < 1) {
    rh_verify_fail('F&B outlet covers chart has no outlets for company ' . $companyId . ' MTD.');
} else {
    rh_verify_pass('F&B outlet covers chart has ' . count($fbCovers['labels']) . ' outlet(s).');
}

$budgetTrend = get_budget_vs_actual_trend();
$budgetTotal = array_sum($budgetTrend['budget'] ?? []);
$actualTotal = array_sum($budgetTrend['actual'] ?? []);
if ($budgetTotal <= 0) {
    rh_verify_fail('Budget vs Actual trend has zero budget total for the current year.');
} else {
    rh_verify_pass('Budget vs Actual trend budget total > 0.');
}
if ($actualTotal <= 0) {
    rh_verify_fail('Budget vs Actual trend has zero actual spend for the current year.');
} else {
    rh_verify_pass('Budget vs Actual trend actual total > 0.');
}

$yoy = get_budget_yoy_comparison();
if (array_sum($yoy['data'] ?? []) <= 0) {
    rh_verify_fail('Year-over-year budget comparison has no annual totals.');
} else {
    rh_verify_pass('Year-over-year budget comparison has annual totals.');
}

$capexOpexSplit = get_capex_opex_annual_budget_split();
if (array_sum($capexOpexSplit['data'] ?? []) <= 0) {
    rh_verify_fail('CAPEX/OPEX annual budget split has zero totals.');
} else {
    rh_verify_pass('CAPEX/OPEX annual budget split has budget totals for year ' . (int) ($capexOpexSplit['year'] ?? 0) . '.');
}

$capexOpexBvA = get_capex_opex_budget_vs_actual();
if (array_sum($capexOpexBvA['budget'] ?? []) <= 0) {
    rh_verify_fail('CAPEX/OPEX budget vs actual chart has zero budget totals.');
} else {
    rh_verify_pass('CAPEX/OPEX budget vs actual chart has budget totals.');
}

$indexPath = ROOT_PATH . 'modules/reports/index.php';
if (!is_file($indexPath)) {
    rh_verify_fail('modules/reports/index.php is missing.');
} else {
    $indexSource = (string)file_get_contents($indexPath);
    $requiredCanvas = [
        'opsOccupancyChart',
        'budgetVsActualChart',
        'capexOpexBudgetSplitChart',
        'capexOpexBudgetVsActualChart',
        'capexOpexMonthlyActualChart',
        'equipmentChart',
        'hrChart',
    ];
    foreach ($requiredCanvas as $canvasId) {
        if (strpos($indexSource, $canvasId) === false) {
            rh_verify_fail('index.php missing canvas #' . $canvasId);
        }
    }
    if ($failures === 0 || strpos($indexSource, 'opsOccupancyChart') !== false) {
        rh_verify_pass('index.php defines core chart canvas elements.');
    }
    if (strpos($indexSource, 'itm_ui_locale_format_money_display') === false) {
        rh_verify_fail('index.php must format money insight cards via itm_ui_locale_format_money_display().');
    } else {
        rh_verify_pass('index.php uses locale money formatter for insight cards.');
    }
    if (strpos($indexSource, 'itmMoneyFormat') === false || strpos($indexSource, 'itmFormatChartMoney') === false) {
        rh_verify_fail('index.php must expose Chart.js locale money formatter helpers.');
    } else {
        rh_verify_pass('index.php defines Chart.js locale money formatter.');
    }
    if (preg_match('/\$<\?php echo number_format\(/', $indexSource)) {
        rh_verify_fail('index.php still hardcodes $ + number_format for money insight cards.');
    } else {
        rh_verify_pass('index.php has no hardcoded dollar number_format insight values.');
    }
}

rh_verify_exit($failures > 0 ? 1 : 0);
