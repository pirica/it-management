<?php
/**
 * 📑 Budget Report Module - Index
 *
 * Why: Finance users need a simple period selector to compare current month
 * actuals against previous month and same month in the previous year.
 */

require '../../config/config.php';

$reportCompanyId = (int)($company_id ?? 0);
$selectedYear = (int)date('Y');
$selectedMonth = 0;
$selectedCostCenterId = 0;
$selectedGlAccountId = 0;
$reportRows = [];
$reportError = '';

// Handle JSON import requests from table-tools.js so this report does not silently
// fall through to HTML rendering when users click 📥Import Excel.
$budgetReportContentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($budgetReportContentType, 'application/json') !== false) {
    $budgetReportRawBody = file_get_contents('php://input');
    $budgetReportJsonBody = json_decode((string)$budgetReportRawBody, true);
    if (is_array($budgetReportJsonBody) && isset($budgetReportJsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');

        $budgetReportToken = (string)($budgetReportJsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($budgetReportToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Database import is not supported in Budget Report because this screen is a computed summary from budget, forecast, and expense source tables.'
        ]);
        exit;
    }
}

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

if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}

$isMonthMode = $selectedMonth >= 1 && $selectedMonth <= 12;
$previousYear = $selectedYear - 1;
$previousMonthYear = $selectedYear;
$previousMonth = 0;
if ($isMonthMode) {
    $reportDate = DateTime::createFromFormat('Y-n-j', $selectedYear . '-' . $selectedMonth . '-1');
    if ($reportDate instanceof DateTime) {
        $prevDate = clone $reportDate;
        $prevDate->modify('-1 month');
        $previousMonthYear = (int)$prevDate->format('Y');
        $previousMonth = (int)$prevDate->format('n');
    } else {
        $previousMonth = max(1, $selectedMonth - 1);
    }
}

$costCenterOptions = [];
$costCenterSql = 'SELECT id, name FROM cost_centers WHERE company_id = ? ORDER BY name ASC';
$costCenterStmt = mysqli_prepare($conn, $costCenterSql);
if ($costCenterStmt) {
    mysqli_stmt_bind_param($costCenterStmt, 'i', $reportCompanyId);
    mysqli_stmt_execute($costCenterStmt);
    $costCenterResult = mysqli_stmt_get_result($costCenterStmt);
    while ($costCenterResult && ($row = mysqli_fetch_assoc($costCenterResult))) {
        $costCenterOptions[] = $row;
    }
    mysqli_stmt_close($costCenterStmt);
}

$glAccountOptions = [];
$glAccountSql = 'SELECT id, account_code, account_name FROM gl_accounts WHERE company_id = ? ORDER BY account_code ASC, account_name ASC';
$glAccountStmt = mysqli_prepare($conn, $glAccountSql);
if ($glAccountStmt) {
    mysqli_stmt_bind_param($glAccountStmt, 'i', $reportCompanyId);
    mysqli_stmt_execute($glAccountStmt);
    $glAccountResult = mysqli_stmt_get_result($glAccountStmt);
    while ($glAccountResult && ($row = mysqli_fetch_assoc($glAccountResult))) {
        $glAccountOptions[] = $row;
    }
    mysqli_stmt_close($glAccountStmt);
}

if ($reportCompanyId <= 0) {
    $reportError = 'Please select an active company before generating the report.';
} else {
    if ($isMonthMode) {
        $reportSql = "SELECT
                cc.name AS cost_center,
                ga.account_code,
                ga.account_name,
                COALESCE(b_cur.amount, 0) AS budget_selected_period,
                COALESCE(f_cur.amount, 0) AS forecast_selected_period,
                COALESCE(a_cur.amount, 0) AS actual_curr_period,
                COALESCE(a_prev.amount, 0) AS actual_prev_period,
                COALESCE(a_prev_year_month.amount, 0) AS actual_prev_year_same_month
            FROM (
                SELECT e.cost_center_id, e.gl_account_id
                FROM expenses e
                WHERE e.company_id = ?
                  AND (
                        (YEAR(e.date) = ? AND MONTH(e.date) = ?)
                     OR (YEAR(e.date) = ? AND MONTH(e.date) = ?)
                     OR (YEAR(e.date) = ? AND MONTH(e.date) = ?)
                  )
                GROUP BY e.cost_center_id, e.gl_account_id
                UNION
                SELECT fr.cost_center_id, fr.gl_account_id
                FROM forecast_revisions fr
                WHERE fr.company_id = ?
                  AND fr.year = ?
                  AND fr.month = ?
                GROUP BY fr.cost_center_id, fr.gl_account_id
                UNION
                SELECT ab.cost_center_id, ab.gl_account_id
                FROM annual_budgets ab
                JOIN monthly_budgets mb
                  ON mb.company_id = ab.company_id
                 AND mb.annual_budget_id = ab.id
                WHERE ab.company_id = ?
                  AND ab.year = ?
                  AND mb.month = ?
                GROUP BY ab.cost_center_id, ab.gl_account_id
            ) base_pairs
            JOIN cost_centers cc
              ON cc.company_id = ?
             AND cc.id = base_pairs.cost_center_id
            JOIN gl_accounts ga
              ON ga.company_id = ?
             AND ga.id = base_pairs.gl_account_id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_cur
                ON a_cur.company_id = cc.company_id
               AND a_cur.cost_center_id = cc.id
               AND a_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_prev
                ON a_prev.company_id = cc.company_id
               AND a_prev.cost_center_id = cc.id
               AND a_prev.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_prev_year_month
                ON a_prev_year_month.company_id = cc.company_id
               AND a_prev_year_month.cost_center_id = cc.id
               AND a_prev_year_month.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(forecast_amount) AS amount
                FROM forecast_revisions
                WHERE company_id = ? AND year = ? AND month = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) f_cur
                ON f_cur.company_id = cc.company_id
               AND f_cur.cost_center_id = cc.id
               AND f_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT ab.company_id, ab.cost_center_id, ab.gl_account_id, SUM(mb.amount) AS amount
                FROM annual_budgets ab
                JOIN monthly_budgets mb
                  ON mb.company_id = ab.company_id
                 AND mb.annual_budget_id = ab.id
                WHERE ab.company_id = ? AND ab.year = ? AND mb.month = ?
                GROUP BY ab.company_id, ab.cost_center_id, ab.gl_account_id
            ) b_cur
                ON b_cur.company_id = cc.company_id
               AND b_cur.cost_center_id = cc.id
               AND b_cur.gl_account_id = ga.id
            WHERE cc.company_id = ?
              AND (? = 0 OR cc.id = ?)
              AND (? = 0 OR ga.id = ?)
            ORDER BY cc.name, ga.account_code";
    } else {
        $reportSql = "SELECT
                cc.name AS cost_center,
                ga.account_code,
                ga.account_name,
                COALESCE(b_cur.amount, 0) AS budget_selected_period,
                COALESCE(f_cur.amount, 0) AS forecast_selected_period,
                COALESCE(a_cur.amount, 0) AS actual_curr_period,
                COALESCE(a_prev.amount, 0) AS actual_prev_period,
                0 AS actual_prev_year_same_month
            FROM (
                SELECT e.cost_center_id, e.gl_account_id
                FROM expenses e
                WHERE e.company_id = ?
                  AND YEAR(e.date) IN (?, ?)
                GROUP BY e.cost_center_id, e.gl_account_id
                UNION
                SELECT fr.cost_center_id, fr.gl_account_id
                FROM forecast_revisions fr
                WHERE fr.company_id = ?
                  AND fr.year = ?
                GROUP BY fr.cost_center_id, fr.gl_account_id
                UNION
                SELECT ab.cost_center_id, ab.gl_account_id
                FROM annual_budgets ab
                WHERE ab.company_id = ?
                  AND ab.year = ?
                GROUP BY ab.cost_center_id, ab.gl_account_id
            ) base_pairs
            JOIN cost_centers cc
              ON cc.company_id = ?
             AND cc.id = base_pairs.cost_center_id
            JOIN gl_accounts ga
              ON ga.company_id = ?
             AND ga.id = base_pairs.gl_account_id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND YEAR(date) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_cur
                ON a_cur.company_id = cc.company_id
               AND a_cur.cost_center_id = cc.id
               AND a_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND YEAR(date) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_prev
                ON a_prev.company_id = cc.company_id
               AND a_prev.cost_center_id = cc.id
               AND a_prev.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(forecast_amount) AS amount
                FROM forecast_revisions
                WHERE company_id = ? AND year = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) f_cur
                ON f_cur.company_id = cc.company_id
               AND f_cur.cost_center_id = cc.id
               AND f_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM annual_budgets
                WHERE company_id = ? AND year = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) b_cur
                ON b_cur.company_id = cc.company_id
               AND b_cur.cost_center_id = cc.id
               AND b_cur.gl_account_id = ga.id
            WHERE cc.company_id = ?
              AND (? = 0 OR cc.id = ?)
              AND (? = 0 OR ga.id = ?)
            ORDER BY cc.name, ga.account_code";
    }

    $reportStmt = mysqli_prepare($conn, $reportSql);
    if ($reportStmt) {
        if ($isMonthMode) {
            mysqli_stmt_bind_param(
                $reportStmt,
                'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii',
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $previousMonthYear,
                $previousMonth,
                $previousYear,
                $selectedMonth,
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $reportCompanyId,
                $reportCompanyId,
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $reportCompanyId,
                $previousMonthYear,
                $previousMonth,
                $reportCompanyId,
                $previousYear,
                $selectedMonth,
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $reportCompanyId,
                $selectedYear,
                $selectedMonth,
                $reportCompanyId,
                $selectedCostCenterId,
                $selectedCostCenterId,
                $selectedGlAccountId,
                $selectedGlAccountId
            );
        } else {
            mysqli_stmt_bind_param(
                $reportStmt,
                'iiiiiiiiiiiiiiiiiiiiii',
                $reportCompanyId,
                $selectedYear,
                $previousYear,
                $reportCompanyId,
                $selectedYear,
                $reportCompanyId,
                $selectedYear,
                $reportCompanyId,
                $reportCompanyId,
                $reportCompanyId,
                $selectedYear,
                $reportCompanyId,
                $previousYear,
                $reportCompanyId,
                $selectedYear,
                $reportCompanyId,
                $selectedYear,
                $reportCompanyId,
                $selectedCostCenterId,
                $selectedCostCenterId,
                $selectedGlAccountId,
                $selectedGlAccountId
            );
        }
        mysqli_stmt_execute($reportStmt);
        $reportResult = mysqli_stmt_get_result($reportStmt);
        while ($reportResult && ($row = mysqli_fetch_assoc($reportResult))) {
            $reportRows[] = $row;
        }
        mysqli_stmt_close($reportStmt);
    } else {
        $reportError = 'Unable to generate report at this time.';
    }
}

$monthOptions = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Report</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>📑 Budget Report</h1>
            </div>

                <div style="margin-bottom:12px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
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

                    <div class="form-actions" style="margin:0;">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>

            <?php if ($reportError !== ''): ?>
                <div class="alert alert-error"><?php echo sanitize($reportError); ?></div>
            <?php endif; ?>

            <div class="card">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <th>Cost Center</th>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Budget (Selected Period)</th>
                        <th>Forecast (Selected Period)</th>
                        <th><?php echo $isMonthMode ? 'Actual (Selected Month)' : 'Actual (Selected Year)'; ?></th>
                        <th><?php echo $isMonthMode ? 'Actual (Previous Month)' : 'Actual (Previous Year)'; ?></th>
                        <th>Actual (Same Month Previous Year)</th>
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
                            <td colspan="11" style="text-align:center;">No data found for the selected filters.</td>
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
