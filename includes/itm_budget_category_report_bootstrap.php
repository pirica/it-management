<?php
/**
 * Shared bootstrap for Budget Report, CAPEX, and OPEX computed finance screens.
 *
 * Caller must set before require:
 * - $itmBcrTitle (browser title base)
 * - $itmBcrHeadingEmoji (visible h1 emoji only)
 * - $itmBcrHeadingTitle (h1 title attribute phrase)
 * - $itmBcrCategoryKind (null|string capex|opex — null = all categories)
 */

if (!isset($itmBcrTitle)) {
    $itmBcrTitle = 'Budget Report';
}
if (!isset($itmBcrHeadingEmoji)) {
    $itmBcrHeadingEmoji = '📑';
}
if (!isset($itmBcrHeadingTitle)) {
    $itmBcrHeadingTitle = 'Budget report';
}
if (!isset($itmBcrCategoryKind)) {
    $itmBcrCategoryKind = null;
}

require_once ROOT_PATH . 'includes/itm_budget_category_report.php';

itm_budget_category_report_handle_import_reject((string)$itmBcrTitle);

itm_budget_category_report_backfill_category_kinds($conn);

$reportCompanyId = (int)($company_id ?? 0);
$selectedYear = itm_budget_category_report_default_year($conn, $reportCompanyId);
$selectedMonth = 0;
$selectedCostCenterId = 0;
$selectedGlAccountId = 0;
$reportRows = [];
$reportError = '';

if (isset($_GET['year'])) {
    $selectedYear = (int)$_GET['year'];
}
if (isset($_GET['month'])) {
    $selectedMonthRaw = trim((string)$_GET['month']);
    $selectedMonth = ($selectedMonthRaw === '') ? 0 : (int)$selectedMonthRaw;
}
if (isset($_GET['cost_center_id'])) {
    $selectedCostCenterId = max(0, (int)$_GET['cost_center_id']);
}
if (isset($_GET['gl_account_id'])) {
    $selectedGlAccountId = max(0, (int)$_GET['gl_account_id']);
}
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'cost_center'));
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';

$costCenterOptions = itm_budget_category_report_cost_center_options($conn, $reportCompanyId);
$glAccountOptions = itm_budget_category_report_gl_account_options($conn, $reportCompanyId, $itmBcrCategoryKind);

$reportResult = itm_budget_category_report_run($conn, [
    'company_id' => $reportCompanyId,
    'year' => $selectedYear,
    'month' => $selectedMonth,
    'cost_center_id' => $selectedCostCenterId,
    'gl_account_id' => $selectedGlAccountId,
    'search' => $search,
    'sort' => $sort,
    'dir' => $dir,
    'category_kind' => $itmBcrCategoryKind,
]);
$reportRows = $reportResult['rows'];
$reportError = (string)($reportResult['error'] ?? '');
$isMonthMode = (bool)($reportResult['is_month_mode'] ?? false);

$monthOptions = itm_budget_category_report_month_options();

$bgrSearchClearHref = htmlspecialchars(
    'index.php?' . http_build_query(
        itm_budget_category_report_filter_query($selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, '')
        + ['sort' => $sort, 'dir' => $dir]
    ),
    ENT_QUOTES,
    'UTF-8'
);

$crud_title = (string)$itmBcrTitle;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title(
        $conn,
        (int)($company_id ?? 0),
        (int)($_SESSION['employee_id'] ?? 0),
        basename(dirname($_SERVER['PHP_SELF'])),
        (string)$crud_title
    );
    ?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .bgr-table thead a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1 title="<?php echo sanitize((string)$itmBcrHeadingTitle); ?>"><?php echo sanitize((string)$itmBcrHeadingEmoji); ?></h1>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:160px;">
                        <label for="reportYear">Year</label>
                        <input type="number" id="reportYear" name="year" min="2000" max="2100" value="<?php echo (int)$selectedYear; ?>">
                    </div>

                    <div class="form-group" style="margin:0;min-width:180px;">
                        <label for="reportMonth">Month (optional)</label>
                        <select id="reportMonth" name="month">
                            <option value="" <?php echo $selectedMonth === 0 ? 'selected' : ''; ?>>All Months</option>
                            <?php foreach ($monthOptions as $monthNumber => $monthLabel): ?>
                                <option value="<?php echo (int)$monthNumber; ?>" <?php echo $selectedMonth === (int)$monthNumber ? 'selected' : ''; ?>>
                                    <?php echo sanitize($monthLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;min-width:240px;">
                        <label for="costCenterSelect">Cost Center (optional)</label>
                        <select id="costCenterSelect" name="cost_center_id">
                            <option value="0">All Cost Centers</option>
                            <?php foreach ($costCenterOptions as $option): ?>
                                <option value="<?php echo (int)$option['id']; ?>" <?php echo $selectedCostCenterId === (int)$option['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize((string)$option['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;min-width:280px;">
                        <label for="glAccountSelect">GL Account (optional)</label>
                        <select id="glAccountSelect" name="gl_account_id">
                            <option value="0">All GL Accounts</option>
                            <?php foreach ($glAccountOptions as $option): ?>
                                <option value="<?php echo (int)$option['id']; ?>" <?php echo $selectedGlAccountId === (int)$option['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize((string)$option['account_code'] . ' - ' . (string)$option['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;min-width:220px;">
                        <label for="search">Search (all fields)</label>
                        <input type="search" name="search" id="search" class="form-control" value="<?php echo sanitize($search); ?>" placeholder="Type to search...">
                    </div>

                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">

                    <div class="form-actions" style="margin:0;display:flex;gap:8px;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary" title="Generate report">Generate Report</button>
                        <button type="submit" class="btn btn-primary" title="🔎 Search">Search</button>
                        <?php if ($search !== ''): ?>
                            <a class="btn" href="<?php echo $bgrSearchClearHref; ?>" title="Clear">🔙</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($reportError !== ''): ?>
                <?php echo itm_render_alert_errors($reportError ?? ''); ?>
            <?php endif; ?>

            <div class="card">
                <table class="bgr-table" data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('cost_center', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Cost Center<?php echo itm_budget_category_report_sort_indicator('cost_center', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('account_code', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Account Code<?php echo itm_budget_category_report_sort_indicator('account_code', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('account_name', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Account Name<?php echo itm_budget_category_report_sort_indicator('account_name', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('budget_selected_period', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Budget (Selected Period)<?php echo itm_budget_category_report_sort_indicator('budget_selected_period', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('forecast_selected_period', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Forecast (Selected Period)<?php echo itm_budget_category_report_sort_indicator('forecast_selected_period', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('actual_curr_period', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>"><?php echo $isMonthMode ? 'Actual (Selected Month)' : 'Actual (Selected Year)'; ?><?php echo itm_budget_category_report_sort_indicator('actual_curr_period', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('actual_prev_period', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>"><?php echo $isMonthMode ? 'Actual (Previous Month)' : 'Actual (Previous Year)'; ?><?php echo itm_budget_category_report_sort_indicator('actual_prev_period', $sort, $dir); ?></a></th>
                        <th><a style="text-decoration:none;color:inherit;" href="<?php echo sanitize(itm_budget_category_report_sort_url('actual_prev_year_same_month', $sort, $dir, $selectedYear, $selectedMonth, $selectedCostCenterId, $selectedGlAccountId, $search)); ?>">Actual (Same Month Previous Year)<?php echo itm_budget_category_report_sort_indicator('actual_prev_year_same_month', $sort, $dir); ?></a></th>
                        <th>Forecast - Actual</th>
                        <th>Budget - Forecast</th>
                        <th>Budget - Actual</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($reportRows) > 0): ?>
                        <?php
                        $totalsBudgetSelectedPeriod = 0.0;
                        $totalsForecastSelectedPeriod = 0.0;
                        $totalsActualCurrPeriod = 0.0;
                        $totalsActualPrevPeriod = 0.0;
                        $totalsActualPrevYearSameMonth = 0.0;
                        $totalsForecastMinusActual = 0.0;
                        $totalsBudgetMinusForecast = 0.0;
                        $totalsBudgetMinusActual = 0.0;
                        ?>
                        <?php foreach ($reportRows as $row): ?>
                            <?php
                            $rowBudgetSelectedPeriod = (float)$row['budget_selected_period'];
                            $rowForecastSelectedPeriod = (float)$row['forecast_selected_period'];
                            $rowActualCurrPeriod = (float)$row['actual_curr_period'];
                            $rowActualPrevPeriod = (float)$row['actual_prev_period'];
                            $rowActualPrevYearSameMonth = (float)$row['actual_prev_year_same_month'];
                            $rowForecastMinusActual = $rowForecastSelectedPeriod - $rowActualCurrPeriod;
                            $rowBudgetMinusForecast = $rowBudgetSelectedPeriod - $rowForecastSelectedPeriod;
                            $rowBudgetMinusActual = $rowBudgetSelectedPeriod - $rowActualCurrPeriod;

                            $totalsBudgetSelectedPeriod += $rowBudgetSelectedPeriod;
                            $totalsForecastSelectedPeriod += $rowForecastSelectedPeriod;
                            $totalsActualCurrPeriod += $rowActualCurrPeriod;
                            $totalsActualPrevPeriod += $rowActualPrevPeriod;
                            $totalsActualPrevYearSameMonth += $rowActualPrevYearSameMonth;
                            $totalsForecastMinusActual += $rowForecastMinusActual;
                            $totalsBudgetMinusForecast += $rowBudgetMinusForecast;
                            $totalsBudgetMinusActual += $rowBudgetMinusActual;
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)$row['cost_center']); ?></td>
                                <td><?php echo sanitize((string)$row['account_code']); ?></td>
                                <td><?php echo sanitize((string)$row['account_name']); ?></td>
                                <td><?php echo number_format($rowBudgetSelectedPeriod, 2); ?></td>
                                <td><?php echo number_format($rowForecastSelectedPeriod, 2); ?></td>
                                <td><?php echo number_format($rowActualCurrPeriod, 2); ?></td>
                                <td><?php echo number_format($rowActualPrevPeriod, 2); ?></td>
                                <td>
                                    <?php if ($isMonthMode): ?>
                                        <?php echo number_format($rowActualPrevYearSameMonth, 2); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($rowForecastMinusActual, 2); ?></td>
                                <td><?php echo number_format($rowBudgetMinusForecast, 2); ?></td>
                                <td><?php echo number_format($rowBudgetMinusActual, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight:700;background-color:rgba(0,0,0,0.03);">
                            <td colspan="3" style="text-align:right;">Totals</td>
                            <td><?php echo number_format($totalsBudgetSelectedPeriod, 2); ?></td>
                            <td><?php echo number_format($totalsForecastSelectedPeriod, 2); ?></td>
                            <td><?php echo number_format($totalsActualCurrPeriod, 2); ?></td>
                            <td><?php echo number_format($totalsActualPrevPeriod, 2); ?></td>
                            <td>
                                <?php if ($isMonthMode): ?>
                                    <?php echo number_format($totalsActualPrevYearSameMonth, 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($totalsForecastMinusActual, 2); ?></td>
                            <td><?php echo number_format($totalsBudgetMinusForecast, 2); ?></td>
                            <td><?php echo number_format($totalsBudgetMinusActual, 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align:center;">
                                No data found for the selected filters.
                                <?php if ($itmBcrCategoryKind === 'capex' || $itmBcrCategoryKind === 'opex'): ?>
                                    <br><span style="opacity:.85;font-size:0.92em;">Check <a href="../budget_categories/index.php" target="_blank" rel="noopener">Budget Categories</a> — Capital Expense must be <strong>CAPEX</strong>, Operating Expense <strong>OPEX</strong>. Seed demo budgets use the latest annual budget year (try <?php echo (int)itm_budget_category_report_default_year($conn, $reportCompanyId); ?>).</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
