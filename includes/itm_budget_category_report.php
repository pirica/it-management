<?php
/**
 * Budget / forecast / expense rollup queries for Budget Report and CAPEX/OPEX modules.
 *
 * Why: One SQL contract for period comparisons; optional category_kind filter scopes GL rows.
 */

if (!function_exists('itm_budget_category_report_kinds')) {
    /**
     * @return array<string, string>
     */
    function itm_budget_category_report_kinds()
    {
        return [
            'revenue' => 'Revenue',
            'opex' => 'Operating expense (OPEX)',
            'capex' => 'Capital expense (CAPEX)',
            'other' => 'Other',
        ];
    }
}

if (!function_exists('itm_budget_category_report_kind_label')) {
    function itm_budget_category_report_kind_label($kind)
    {
        $kinds = itm_budget_category_report_kinds();
        $key = strtolower(trim((string)$kind));

        return $kinds[$key] ?? $key;
    }
}

if (!function_exists('itm_budget_category_report_month_options')) {
    /**
     * @return array<int, string>
     */
    function itm_budget_category_report_month_options()
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }
}

if (!function_exists('itm_budget_category_report_sort_map')) {
    /**
     * @return array<string, string>
     */
    function itm_budget_category_report_sort_map()
    {
        return [
            'cost_center' => 'cc.name',
            'account_code' => 'ga.account_code',
            'account_name' => 'ga.account_name',
            'budget_selected_period' => 'budget_selected_period',
            'forecast_selected_period' => 'forecast_selected_period',
            'actual_curr_period' => 'actual_curr_period',
            'actual_prev_period' => 'actual_prev_period',
            'actual_prev_year_same_month' => 'actual_prev_year_same_month',
        ];
    }
}

if (!function_exists('itm_budget_category_report_cost_center_options')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_budget_category_report_cost_center_options($conn, $companyId)
    {
        $options = [];
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return $options;
        }

        $sql = 'SELECT id, name FROM cost_centers WHERE company_id = ? ORDER BY name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $options;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $options[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $options;
    }
}

if (!function_exists('itm_budget_category_report_gl_account_options')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_budget_category_report_gl_account_options($conn, $companyId, $categoryKind = null)
    {
        $options = [];
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return $options;
        }

        $categoryKind = strtolower(trim((string)$categoryKind));
        if ($categoryKind !== '' && !array_key_exists($categoryKind, itm_budget_category_report_kinds())) {
            $categoryKind = '';
        }

        if ($categoryKind !== '') {
            $sql = 'SELECT ga.id, ga.account_code, ga.account_name
                FROM gl_accounts ga
                INNER JOIN budget_categories bc
                  ON bc.company_id = ga.company_id
                 AND bc.id = ga.category_id
                 AND bc.category_kind = ?
                WHERE ga.company_id = ?
                ORDER BY ga.account_code ASC, ga.account_name ASC';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return $options;
            }
            mysqli_stmt_bind_param($stmt, 'si', $categoryKind, $companyId);
        } else {
            $sql = 'SELECT id, account_code, account_name FROM gl_accounts WHERE company_id = ? ORDER BY account_code ASC, account_name ASC';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return $options;
            }
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $options[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $options;
    }
}

if (!function_exists('itm_budget_category_report_handle_import_reject')) {
    function itm_budget_category_report_handle_import_reject($moduleLabel)
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strpos($contentType, 'application/json') === false) {
            return;
        }

        $rawBody = file_get_contents('php://input');
        $jsonBody = json_decode((string)$rawBody, true);
        if (!is_array($jsonBody) || !isset($jsonBody['import_excel_rows'])) {
            return;
        }

        header('Content-Type: application/json');

        $token = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Database import is not supported in ' . $moduleLabel . ' because this screen is a computed summary from budget, forecast, and expense source tables.',
        ]);
        exit;
    }
}

if (!function_exists('itm_budget_category_report_filter_query')) {
    /**
     * @return array<string, mixed>
     */
    function itm_budget_category_report_filter_query($year, $month, $costCenterId, $glAccountId, $search)
    {
        $query = [
            'year' => $year,
            'cost_center_id' => $costCenterId,
            'gl_account_id' => $glAccountId,
        ];
        if ($month >= 1 && $month <= 12) {
            $query['month'] = $month;
        }
        if ($search !== '') {
            $query['search'] = $search;
        }

        return $query;
    }
}

if (!function_exists('itm_budget_category_report_sort_url')) {
    function itm_budget_category_report_sort_url($column, $currentSort, $currentDir, $year, $month, $costCenterId, $glAccountId, $search)
    {
        $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
        $query = itm_budget_category_report_filter_query($year, $month, $costCenterId, $glAccountId, $search);
        $query['sort'] = $column;
        $query['dir'] = $nextDir;

        return 'index.php?' . http_build_query($query);
    }
}

if (!function_exists('itm_budget_category_report_sort_indicator')) {
    function itm_budget_category_report_sort_indicator($column, $currentSort, $currentDir)
    {
        if ($currentSort !== $column) {
            return '';
        }

        return $currentDir === 'ASC' ? ' ▲' : ' ▼';
    }
}

if (!function_exists('itm_budget_category_report_run')) {
    /**
     * @param array<string, mixed> $options
     * @return array{rows: array<int, array<string, mixed>>, error: string, is_month_mode: bool}
     */
    function itm_budget_category_report_run($conn, array $options)
    {
        $companyId = (int)($options['company_id'] ?? 0);
        $year = (int)($options['year'] ?? (int)date('Y'));
        $month = (int)($options['month'] ?? 0);
        $costCenterId = max(0, (int)($options['cost_center_id'] ?? 0));
        $glAccountId = max(0, (int)($options['gl_account_id'] ?? 0));
        $search = trim((string)($options['search'] ?? ''));
        $sort = trim((string)($options['sort'] ?? 'cost_center'));
        $dir = strtoupper(trim((string)($options['dir'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';
        $categoryKind = strtolower(trim((string)($options['category_kind'] ?? '')));
        if ($categoryKind !== '' && !array_key_exists($categoryKind, itm_budget_category_report_kinds())) {
            $categoryKind = '';
        }

        $rows = [];
        $error = '';

        if ($companyId <= 0) {
            return [
                'rows' => [],
                'error' => 'Please select an active company before generating the report.',
                'is_month_mode' => false,
            ];
        }

        $sortMap = itm_budget_category_report_sort_map();
        if (!isset($sortMap[$sort])) {
            $sort = 'cost_center';
        }
        $sortSql = $sortMap[$sort] . ' ' . $dir;
        if ($sort === 'cost_center') {
            $sortSql .= ', ga.account_code ASC';
        } elseif ($sort === 'account_code') {
            $sortSql .= ', ga.account_name ASC';
        }

        $reportSearchSql = '';
        $reportSearchBindTypes = '';
        $reportSearchBindParams = [];
        if ($search !== '') {
            $searchPattern = (strpos($search, '%') !== false || strpos($search, '_') !== false) ? $search : '%' . $search . '%';
            $reportSearchSql = ' AND (cc.name LIKE ? OR ga.account_code LIKE ? OR ga.account_name LIKE ?)';
            $reportSearchBindTypes = 'sss';
            $reportSearchBindParams = [$searchPattern, $searchPattern, $searchPattern];
        }

        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 0 || $month > 12) {
            $month = 0;
        }

        $isMonthMode = $month >= 1 && $month <= 12;
        $previousYear = $year - 1;
        $previousMonthYear = $year;
        $previousMonth = 0;
        if ($isMonthMode) {
            $reportDate = DateTime::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
            if ($reportDate instanceof DateTime) {
                $prevDate = clone $reportDate;
                $prevDate->modify('-1 month');
                $previousMonthYear = (int)$prevDate->format('Y');
                $previousMonth = (int)$prevDate->format('n');
            } else {
                $previousMonth = max(1, $month - 1);
            }
        }

        $expenseActualFilterSql = '';
        $actualPaidIds = itm_expenses_paid_status_ids_for_actuals($conn, $companyId);
        if ($actualPaidIds !== []) {
            $expenseActualFilterSql = ' AND paid_status_id IN (' . implode(',', array_map('intval', $actualPaidIds)) . ')';
        }

        $categoryJoinSql = '';
        $categoryFilterSql = '';
        if ($categoryKind !== '') {
            $categoryJoinSql = ' JOIN budget_categories bc
              ON bc.company_id = ga.company_id
             AND bc.id = ga.category_id';
            $categoryFilterSql = ' AND bc.category_kind = ?';
        }

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
                  AND e.deleted_at IS NULL
                  {$expenseActualFilterSql}
                  AND (
                        (YEAR(COALESCE(e.posting_date, e.date)) = ? AND MONTH(COALESCE(e.posting_date, e.date)) = ?)
                     OR (YEAR(COALESCE(e.posting_date, e.date)) = ? AND MONTH(COALESCE(e.posting_date, e.date)) = ?)
                     OR (YEAR(COALESCE(e.posting_date, e.date)) = ? AND MONTH(COALESCE(e.posting_date, e.date)) = ?)
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
             AND ga.id = base_pairs.gl_account_id"
                . $categoryJoinSql
                . " LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND deleted_at IS NULL{$expenseActualFilterSql} AND YEAR(COALESCE(posting_date, date)) = ? AND MONTH(COALESCE(posting_date, date)) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_cur
                ON a_cur.company_id = cc.company_id
               AND a_cur.cost_center_id = cc.id
               AND a_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND deleted_at IS NULL{$expenseActualFilterSql} AND YEAR(COALESCE(posting_date, date)) = ? AND MONTH(COALESCE(posting_date, date)) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_prev
                ON a_prev.company_id = cc.company_id
               AND a_prev.cost_center_id = cc.id
               AND a_prev.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND deleted_at IS NULL{$expenseActualFilterSql} AND YEAR(COALESCE(posting_date, date)) = ? AND MONTH(COALESCE(posting_date, date)) = ?
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
              AND (? = 0 OR ga.id = ?)"
                . $categoryFilterSql
                . $reportSearchSql
                . ' ORDER BY ' . $sortSql;
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
                  AND e.deleted_at IS NULL
                  {$expenseActualFilterSql}
                  AND YEAR(COALESCE(e.posting_date, e.date)) IN (?, ?)
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
             AND ga.id = base_pairs.gl_account_id"
                . $categoryJoinSql
                . " LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND deleted_at IS NULL{$expenseActualFilterSql} AND YEAR(COALESCE(posting_date, date)) = ?
                GROUP BY company_id, cost_center_id, gl_account_id
            ) a_cur
                ON a_cur.company_id = cc.company_id
               AND a_cur.cost_center_id = cc.id
               AND a_cur.gl_account_id = ga.id
            LEFT JOIN (
                SELECT company_id, cost_center_id, gl_account_id, SUM(amount) AS amount
                FROM expenses
                WHERE company_id = ? AND deleted_at IS NULL{$expenseActualFilterSql} AND YEAR(COALESCE(posting_date, date)) = ?
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
              AND (? = 0 OR ga.id = ?)"
                . $categoryFilterSql
                . $reportSearchSql
                . ' ORDER BY ' . $sortSql;
        }

        $reportStmt = mysqli_prepare($conn, $reportSql);
        if (!$reportStmt) {
            return [
                'rows' => [],
                'error' => 'Unable to generate report at this time.',
                'is_month_mode' => $isMonthMode,
            ];
        }

        if ($isMonthMode) {
            $reportTypes = 'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii';
            $reportParams = [
                $companyId,
                $year,
                $month,
                $previousMonthYear,
                $previousMonth,
                $previousYear,
                $month,
                $companyId,
                $year,
                $month,
                $companyId,
                $year,
                $month,
                $companyId,
                $companyId,
                $companyId,
                $year,
                $month,
                $companyId,
                $previousMonthYear,
                $previousMonth,
                $companyId,
                $previousYear,
                $month,
                $companyId,
                $year,
                $month,
                $companyId,
                $year,
                $month,
                $companyId,
                $costCenterId,
                $costCenterId,
                $glAccountId,
                $glAccountId,
            ];
        } else {
            $reportTypes = 'iiiiiiiiiiiiiiiiiiiiii';
            $reportParams = [
                $companyId,
                $year,
                $previousYear,
                $companyId,
                $year,
                $companyId,
                $year,
                $companyId,
                $companyId,
                $companyId,
                $year,
                $companyId,
                $previousYear,
                $companyId,
                $year,
                $companyId,
                $year,
                $companyId,
                $costCenterId,
                $costCenterId,
                $glAccountId,
                $glAccountId,
            ];
        }

        if ($categoryKind !== '') {
            $reportTypes .= 's';
            $reportParams[] = $categoryKind;
        }

        if ($reportSearchBindTypes !== '') {
            $reportTypes .= $reportSearchBindTypes;
            $reportParams = array_merge($reportParams, $reportSearchBindParams);
        }

        mysqli_stmt_bind_param($reportStmt, $reportTypes, ...$reportParams);
        mysqli_stmt_execute($reportStmt);
        $reportResult = mysqli_stmt_get_result($reportStmt);
        while ($reportResult && ($row = mysqli_fetch_assoc($reportResult))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($reportStmt);

        return [
            'rows' => $rows,
            'error' => $error,
            'is_month_mode' => $isMonthMode,
        ];
    }
}
