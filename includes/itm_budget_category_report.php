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

if (!function_exists('itm_budget_category_report_category_kind_column_exists')) {
    function itm_budget_category_report_category_kind_column_exists($conn)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        return function_exists('itm_table_has_column')
            && itm_table_has_column($conn, 'budget_categories', 'category_kind');
    }
}

if (!function_exists('itm_budget_category_report_backfill_category_kinds')) {
    /**
     * Map seeded category display names to category_kind on live DBs that predate DML backfill.
     *
     * @return int Rows updated
     */
    function itm_budget_category_report_backfill_category_kinds($conn)
    {
        if (!($conn instanceof mysqli) || !itm_budget_category_report_category_kind_column_exists($conn)) {
            return 0;
        }

        $nameKindMap = [
            'Revenue' => 'revenue',
            'Operating Expense' => 'opex',
            'Capital Expense' => 'capex',
        ];
        $updated = 0;

        foreach ($nameKindMap as $name => $kind) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE budget_categories SET category_kind = ? WHERE name = ? AND category_kind <> ?'
            );
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'sss', $kind, $name, $kind);
            mysqli_stmt_execute($stmt);
            $updated += (int)mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
        }

        return $updated;
    }
}

if (!function_exists('itm_budget_category_report_default_year')) {
    /**
     * Prefer the latest annual budget year for the tenant so seeded demo rows appear on first load.
     */
    function itm_budget_category_report_default_year($conn, $companyId)
    {
        $calendarYear = (int)date('Y');
        $companyId = (int)$companyId;
        if ($companyId <= 0 || !($conn instanceof mysqli)) {
            return $calendarYear;
        }

        $stmt = mysqli_prepare($conn, 'SELECT MAX(year) AS max_year FROM annual_budgets WHERE company_id = ?');
        if (!$stmt) {
            return $calendarYear;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        $maxYear = (int)($row['max_year'] ?? 0);
        if ($maxYear >= 2000 && $maxYear <= 2100) {
            return $maxYear;
        }

        return $calendarYear;
    }
}

if (!function_exists('itm_budget_category_report_ensure_capital_annual_budget_rows')) {
    /**
     * Insert Infrastructure / GL 7100 annual + January monthly budgets when GL exists but no annual row for $year.
     *
     * @return int Annual budget rows inserted
     */
    function itm_budget_category_report_ensure_capital_annual_budget_rows($conn, $year)
    {
        if (!($conn instanceof mysqli)) {
            return 0;
        }

        $year = (int)$year;
        if ($year < 2000 || $year > 2100) {
            return 0;
        }

        $inserted = 0;
        $sql = "INSERT INTO annual_budgets (company_id, cost_center_id, gl_account_id, year, amount, created_by, active, created_at)
            SELECT ga.company_id, cc.id, ga.id, ?, 120000.00, NULL, 1, NOW()
            FROM gl_accounts ga
            INNER JOIN cost_centers cc
              ON cc.company_id = ga.company_id
             AND cc.code = 'CC-IT-INFRA'
             AND cc.name = 'Infrastructure'
            WHERE ga.account_code = '7100'
              AND NOT EXISTS (
                SELECT 1 FROM annual_budgets ab
                WHERE ab.company_id = ga.company_id
                  AND ab.cost_center_id = cc.id
                  AND ab.gl_account_id = ga.id
                  AND ab.year = ?
              )";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $year, $year);
        mysqli_stmt_execute($stmt);
        $inserted = (int)mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($inserted <= 0) {
            return 0;
        }

        $monthSql = "INSERT INTO monthly_budgets (company_id, annual_budget_id, month, amount, active, created_at)
            SELECT ab.company_id, ab.id, 1, 10000.00, 1, NOW()
            FROM annual_budgets ab
            INNER JOIN gl_accounts ga ON ga.company_id = ab.company_id AND ga.id = ab.gl_account_id
            INNER JOIN cost_centers cc ON cc.company_id = ab.company_id AND cc.id = ab.cost_center_id
            WHERE ga.account_code = '7100'
              AND cc.code = 'CC-IT-INFRA'
              AND ab.year = ?
              AND NOT EXISTS (
                SELECT 1 FROM monthly_budgets mb
                WHERE mb.company_id = ab.company_id
                  AND mb.annual_budget_id = ab.id
                  AND mb.month = 1
              )";
        $monthStmt = mysqli_prepare($conn, $monthSql);
        if ($monthStmt) {
            mysqli_stmt_bind_param($monthStmt, 'i', $year);
            mysqli_stmt_execute($monthStmt);
            mysqli_stmt_close($monthStmt);
        }

        return $inserted;
    }
}

if (!function_exists('itm_budget_category_report_seed_company_ids')) {
    /**
     * @return array<int, int>
     */
    function itm_budget_category_report_seed_company_ids($conn, $companyId = null)
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        if ($companyId !== null && (int)$companyId > 0) {
            return [(int)$companyId];
        }

        $ids = [];
        $res = mysqli_query($conn, 'SELECT id FROM companies WHERE id BETWEEN 1 AND 5 ORDER BY id');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }

        return $ids;
    }
}

if (!function_exists('itm_budget_category_report_ensure_demo_sample_rows')) {
    /**
     * Insert CAPEX/OPEX demo budgets (GL 6100/6200/7100), January monthly splits, and one Posted 6100 expense per company.
     *
     * @param int|null $companyId One tenant, or null for seed companies 1–5.
     * @return array{annual:int,monthly:int,expenses:int,companies:int}
     */
    function itm_budget_category_report_ensure_demo_sample_rows($conn, $year, $companyId = null)
    {
        $stats = ['annual' => 0, 'monthly' => 0, 'expenses' => 0, 'companies' => 0];
        if (!($conn instanceof mysqli)) {
            return $stats;
        }

        $year = (int)$year;
        if ($year < 2000 || $year > 2100) {
            return $stats;
        }

        itm_budget_category_report_backfill_category_kinds($conn);

        $companyIds = itm_budget_category_report_seed_company_ids($conn, $companyId);
        if ($companyIds === []) {
            return $stats;
        }

        $categoryTemplates = [
            ['Revenue', 'Revenue-related general ledger accounts', 'revenue'],
            ['Operating Expense', 'Operational expense accounts', 'opex'],
            ['Capital Expense', 'Capital expense accounts', 'capex'],
        ];

        $glTemplates = [
            ['6100', 'IT Maintenance Contracts', 'Operating Expense', 48000.00, 4000.00],
            ['6200', 'Software Licensing', 'Operating Expense', 36000.00, 3000.00],
            ['7100', 'Capital IT Equipment', 'Capital Expense', 120000.00, 10000.00],
        ];

        foreach ($companyIds as $cid) {
            $stats['companies']++;

            foreach ($categoryTemplates as $categoryTemplate) {
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO budget_categories (company_id, name, description, category_kind, active, created_at)
                    SELECT ?, ?, ?, ?, 1, NOW()
                    FROM DUAL
                    WHERE NOT EXISTS (
                        SELECT 1 FROM budget_categories bc
                        WHERE bc.company_id = ? AND bc.name = ?
                    )'
                );
                if (!$stmt) {
                    continue;
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'isssis',
                    $cid,
                    $categoryTemplate[0],
                    $categoryTemplate[1],
                    $categoryTemplate[2],
                    $cid,
                    $categoryTemplate[0]
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            foreach ($glTemplates as $glTemplate) {
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO gl_accounts (company_id, account_code, account_name, category_id, active, created_at)
                    SELECT ?, ?, ?, bc.id, 1, NOW()
                    FROM budget_categories bc
                    WHERE bc.company_id = ? AND bc.name = ?
                      AND NOT EXISTS (
                        SELECT 1 FROM gl_accounts ga
                        WHERE ga.company_id = ? AND ga.account_code = ?
                      )'
                );
                if (!$stmt) {
                    continue;
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'ississ',
                    $cid,
                    $glTemplate[0],
                    $glTemplate[1],
                    $cid,
                    $glTemplate[2],
                    $cid,
                    $glTemplate[0]
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            foreach ($glTemplates as $glTemplate) {
                $annualSql = "INSERT INTO annual_budgets (company_id, cost_center_id, gl_account_id, year, amount, created_by, active, created_at)
                    SELECT ?, cc.id, ga.id, ?, ?, NULL, 1, NOW()
                    FROM gl_accounts ga
                    INNER JOIN cost_centers cc
                      ON cc.company_id = ga.company_id
                     AND cc.code = 'CC-IT-INFRA'
                     AND cc.name = 'Infrastructure'
                    WHERE ga.company_id = ?
                      AND ga.account_code = ?
                      AND NOT EXISTS (
                        SELECT 1 FROM annual_budgets ab
                        WHERE ab.company_id = ga.company_id
                          AND ab.cost_center_id = cc.id
                          AND ab.gl_account_id = ga.id
                          AND ab.year = ?
                      )";
                $annualStmt = mysqli_prepare($conn, $annualSql);
                if (!$annualStmt) {
                    continue;
                }
                $annualAmount = (float)$glTemplate[3];
                mysqli_stmt_bind_param(
                    $annualStmt,
                    'iidisi',
                    $cid,
                    $year,
                    $annualAmount,
                    $cid,
                    $glTemplate[0],
                    $year
                );
                mysqli_stmt_execute($annualStmt);
                $stats['annual'] += (int)mysqli_stmt_affected_rows($annualStmt);
                mysqli_stmt_close($annualStmt);

                $monthSql = "INSERT INTO monthly_budgets (company_id, annual_budget_id, month, amount, active, created_at)
                    SELECT ab.company_id, ab.id, 1, ?, 1, NOW()
                    FROM annual_budgets ab
                    INNER JOIN gl_accounts ga ON ga.company_id = ab.company_id AND ga.id = ab.gl_account_id
                    INNER JOIN cost_centers cc ON cc.company_id = ab.company_id AND cc.id = ab.cost_center_id
                    WHERE ab.company_id = ?
                      AND ab.year = ?
                      AND ga.account_code = ?
                      AND cc.code = 'CC-IT-INFRA'
                      AND NOT EXISTS (
                        SELECT 1 FROM monthly_budgets mb
                        WHERE mb.company_id = ab.company_id
                          AND mb.annual_budget_id = ab.id
                          AND mb.month = 1
                      )";
                $monthStmt = mysqli_prepare($conn, $monthSql);
                if ($monthStmt) {
                    $monthAmount = (float)$glTemplate[4];
                    mysqli_stmt_bind_param($monthStmt, 'diis', $monthAmount, $cid, $year, $glTemplate[0]);
                    mysqli_stmt_execute($monthStmt);
                    $stats['monthly'] += (int)mysqli_stmt_affected_rows($monthStmt);
                    mysqli_stmt_close($monthStmt);
                }
            }

            $expenseSql = "INSERT INTO expenses (
                    company_id, cost_center_id, gl_account_id, date, posting_date, invoice_date,
                    amount, paid_status_id, currency_code, exchange_rate, description, invoice_number,
                    created_by, active, created_at
                )
                SELECT ?, cc.id, ga.id, ?, ?, ?, 3890.00, ps.id, 'EUR', 1.000000,
                    'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', NULL, 1, NOW()
                FROM gl_accounts ga
                INNER JOIN cost_centers cc
                  ON cc.company_id = ga.company_id
                 AND cc.code = 'CC-IT-INFRA'
                 AND cc.name = 'Infrastructure'
                INNER JOIN paid_statuses ps
                  ON ps.company_id = ga.company_id
                 AND ps.name = 'Posted'
                WHERE ga.company_id = ?
                  AND ga.account_code = '6100'
                  AND NOT EXISTS (
                    SELECT 1 FROM expenses e
                    WHERE e.company_id = ga.company_id
                      AND e.cost_center_id = cc.id
                      AND e.gl_account_id = ga.id
                      AND e.invoice_number = 'INV-IT-2026-0001'
                  )";
            $expenseDate = sprintf('%04d-01-15', $year);
            $expenseStmt = mysqli_prepare($conn, $expenseSql);
            if ($expenseStmt) {
                mysqli_stmt_bind_param($expenseStmt, 'isssi', $cid, $expenseDate, $expenseDate, $expenseDate, $cid);
                mysqli_stmt_execute($expenseStmt);
                $stats['expenses'] += (int)mysqli_stmt_affected_rows($expenseStmt);
                mysqli_stmt_close($expenseStmt);
            }
        }

        return $stats;
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
