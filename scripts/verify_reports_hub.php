<?php
/**
 * Reports Hub regression checks — helper payloads and chart seed coverage.
 *
 * Browser: scripts/verify_reports_hub.php
 * CLI: php scripts/verify_reports_hub.php
 *
 * Optional: ITM_TEST_COMPANY_ID (default 1)
 */

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
        'Hotel Operations MTD total_revenue is zero — import database.sql Reports Hub ops_report seeds or add ops_report rows for the current month.'
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

$indexPath = ROOT_PATH . 'modules/reports/index.php';
if (!is_file($indexPath)) {
    rh_verify_fail('modules/reports/index.php is missing.');
} else {
    $indexSource = (string)file_get_contents($indexPath);
    $requiredCanvas = [
        'opsOccupancyChart',
        'budgetVsActualChart',
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
}

rh_verify_exit($failures > 0 ? 1 : 0);
