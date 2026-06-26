<?php
/**
 * Reports Hub Helpers
 * @file modules/reports/api/helpers.php
 */

/**
 * Equipment statistics by type
 */
function get_equipment_statistics() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT et.name, COUNT(*) as count
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY et.name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Ticket status statistics
 */
function get_ticket_statistics() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT ts.name, COUNT(*) as count
            FROM tickets t
            JOIN ticket_statuses ts ON t.status_id = ts.id
            WHERE t.company_id = ?
            GROUP BY ts.name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Employee department distribution
 */
function get_hr_statistics() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT COALESCE(d.name, 'Unassigned') as dept_name, COUNT(*) as count
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.company_id = ?
            GROUP BY dept_name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['dept_name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Network device type counts
 */
function get_network_device_counts() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT et.name, COUNT(*) as count
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY et.name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Budget distribution by category
 */
function get_budget_statistics() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT bc.name, SUM(ab.amount) as total
            FROM annual_budgets ab
            JOIN gl_accounts gl ON ab.gl_account_id = gl.id
            JOIN budget_categories bc ON gl.category_id = bc.id
            WHERE ab.company_id = ?
            GROUP BY bc.name
            ORDER BY total DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (float)$row['total'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Equipment per location
 */
function get_floorplan_location_data() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT COALESCE(l.name, 'No Location') as loc_name, COUNT(*) as count
            FROM equipment e
            LEFT JOIN it_locations l ON e.location_id = l.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY loc_name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['loc_name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Inventory stock distribution
 */
function get_inventory_stock_levels() {
    global $conn, $company_id;

    $low = 0;
    $normal = 0;
    $high = 0;

    $sql = "SELECT quantity_on_hand, quantity_minimum
            FROM inventory_items
            WHERE company_id = ? AND active = 1";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $on_hand = (int)$row['quantity_on_hand'];
            $min = (int)$row['quantity_minimum'];

            if ($on_hand <= $min) {
                $low++;
            } elseif ($on_hand > $min * 3) {
                $high++;
            } else {
                $normal++;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return [
        'labels' => ['Low Stock', 'Normal', 'High'],
        'data' => [$low, $normal, $high]
    ];
}

/**
 * License statistics
 */
function get_license_statistics() {
    global $conn, $company_id;

    $active = 0;
    $expiring = 0;
    $expired = 0;

    $sql = "SELECT expiry_date, active
            FROM license_management
            WHERE company_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $today = new DateTime();
        $soon = (new DateTime())->modify('+30 days');

        while ($row = mysqli_fetch_assoc($result)) {
            if (!$row['active']) {
                continue;
            }

            if (empty($row['expiry_date'])) {
                $active++;
                continue;
            }

            $expiry = new DateTime($row['expiry_date']);
            if ($expiry < $today) {
                $expired++;
            } elseif ($expiry < $soon) {
                $expiring++;
            } else {
                $active++;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return [
        'labels' => ['Active', 'Expiring Soon', 'Expired'],
        'data' => [$active, $expiring, $expired]
    ];
}

/**
 * Budget distribution by department for the current year
 */
function get_budget_by_department() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT d.name, SUM(ab.amount) as total
            FROM annual_budgets ab
            JOIN cost_centers cc ON ab.cost_center_id = cc.id
            JOIN departments d ON cc.department_id = d.id
            WHERE ab.company_id = ? AND ab.year = YEAR(CURDATE()) AND ab.active = 1
            GROUP BY d.name
            ORDER BY total DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (float)$row['total'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Budget vs Actual monthly trend for current year
 */
function get_budget_vs_actual_trend() {
    global $conn, $company_id;

    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
    $budget_data = array_fill(1, 12, 0);
    $actual_data = array_fill(1, 12, 0);

    // Get Budget
    $sql_budget = "SELECT mb.month, SUM(mb.amount) as total
                   FROM monthly_budgets mb
                   JOIN annual_budgets ab ON mb.annual_budget_id = ab.id
                   WHERE mb.company_id = ? AND ab.year = YEAR(CURDATE()) AND mb.active = 1
                   GROUP BY mb.month";
    $stmt = mysqli_prepare($conn, $sql_budget);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $budget_data[(int)$row['month']] = (float)$row['total'];
        }
        mysqli_stmt_close($stmt);
    }

    // Get Actual
    $sql_actual = "SELECT MONTH(e.date) as month, SUM(e.amount) as total
                   FROM expenses e
                   WHERE e.company_id = ? AND YEAR(e.date) = YEAR(CURDATE()) AND e.active = 1
                   GROUP BY month";
    $stmt = mysqli_prepare($conn, $sql_actual);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $actual_data[(int)$row['month']] = (float)$row['total'];
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'labels' => array_values($months),
        'budget' => array_values($budget_data),
        'actual' => array_values($actual_data)
    ];
}

/**
 * Budget Year-over-Year comparison
 */
function get_budget_yoy_comparison() {
    global $conn, $company_id;

    $current_year = (int)date('Y');
    $last_year = $current_year - 1;

    $data = [
        $last_year => 0,
        $current_year => 0
    ];

    $sql = "SELECT ab.year, SUM(ab.amount) as total
            FROM annual_budgets ab
            WHERE ab.company_id = ? AND ab.year IN (?, ?) AND ab.active = 1
            GROUP BY ab.year";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iii", $company_id, $last_year, $current_year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data[(int)$row['year']] = (float)$row['total'];
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'labels' => [(string)$last_year, (string)$current_year],
        'data' => [$data[$last_year], $data[$current_year]]
    ];
}

/**
 * Asset financial value by equipment type
 */
function get_asset_financial_value() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT et.name, SUM(e.purchase_cost) as total_value
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY et.name
            ORDER BY total_value DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (float)$row['total_value'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Maintenance forecast: warranty and license expiries next 6 months
 */
function get_upcoming_maintenance_forecast() {
    global $conn, $company_id;

    $months = [];
    for ($i = 0; $i < 6; $i++) {
        $m = (int)date('n', strtotime("+$i month"));
        $months[$m] = date('M', strtotime("+$i month"));
    }

    $warranty_counts = array_fill_keys(array_keys($months), 0);
    $license_counts = array_fill_keys(array_keys($months), 0);

    // Warranty
    $sql_w = "SELECT MONTH(warranty_expiry) as m, COUNT(*) as count
              FROM equipment
              WHERE company_id = ?
              AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
              AND active = 1
              GROUP BY m";
    $stmt = mysqli_prepare($conn, $sql_w);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($warranty_counts[(int)$row['m']])) {
                $warranty_counts[(int)$row['m']] = (int)$row['count'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Licenses
    $sql_l = "SELECT MONTH(expiry_date) as m, COUNT(*) as count
              FROM license_management
              WHERE company_id = ?
              AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
              AND active = 1
              GROUP BY m";
    $stmt = mysqli_prepare($conn, $sql_l);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($license_counts[(int)$row['m']])) {
                $license_counts[(int)$row['m']] = (int)$row['count'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'labels' => array_values($months),
        'warranty' => array_values($warranty_counts),
        'licenses' => array_values($license_counts)
    ];
}

/**
 * Employee growth trend last 12 months
 */
function get_employee_growth_trend() {
    global $conn, $company_id;

    $labels = [];
    $data = [];

    // Last 12 months including current
    for ($i = 11; $i >= 0; $i--) {
        $labels[] = date('Y-m', strtotime("-$i month"));
    }
    $counts = array_fill_keys($labels, 0);

    $sql = "SELECT DATE_FORMAT(start_date, '%Y-%m') as month_str, COUNT(*) as count
            FROM employees
            WHERE company_id = ? AND start_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 MONTH), '%Y-%m-01')
            GROUP BY month_str";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($counts[$row['month_str']])) {
                $counts[$row['month_str']] = (int)$row['count'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Cumulative growth calculation (simplified: just monthly new hires)
    return [
        'labels' => array_keys($counts),
        'data' => array_values($counts)
    ];
}

/**
 * Monthly performance comparison: This Month This Year vs This Month Last Year (Actual Expenses)
 */
function get_monthly_actual_comparison() {
    global $conn, $company_id;

    $this_month = (int)date('m');
    $this_year = (int)date('Y');
    $last_year = $this_year - 1;

    $this_year_total = 0;
    $last_year_total = 0;

    // This year
    $sql1 = "SELECT SUM(amount) as total FROM expenses
             WHERE company_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND active = 1";
    $stmt1 = mysqli_prepare($conn, $sql1);
    if ($stmt1) {
        mysqli_stmt_bind_param($stmt1, "iii", $company_id, $this_month, $this_year);
        mysqli_stmt_execute($stmt1);
        $res1 = mysqli_stmt_get_result($stmt1);
        if ($row = mysqli_fetch_assoc($res1)) {
            $this_year_total = (float)$row['total'];
        }
        mysqli_stmt_close($stmt1);
    }

    // Last year
    $sql2 = "SELECT SUM(amount) as total FROM expenses
             WHERE company_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND active = 1";
    $stmt2 = mysqli_prepare($conn, $sql2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "iii", $company_id, $this_month, $last_year);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        if ($row = mysqli_fetch_assoc($res2)) {
            $last_year_total = (float)$row['total'];
        }
        mysqli_stmt_close($stmt2);
    }

    return [
        'month_name' => date('F'),
        'this_year' => $this_year_total,
        'last_year' => $last_year_total,
        'labels' => [ (string)$last_year, (string)$this_year ],
        'data' => [ $last_year_total, $this_year_total ]
    ];
}

/**
 * Equipment by status
 */
function get_equipment_status_statistics() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT es.name, COUNT(*) as count
            FROM equipment e
            JOIN equipment_statuses es ON e.status_id = es.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY es.name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Monthly asset additions (last 12 months)
 */
function get_monthly_asset_additions() {
    global $conn, $company_id;

    $labels = [];
    for ($i = 11; $i >= 0; $i--) {
        $labels[] = date('Y-m', strtotime("-$i month"));
    }
    $counts = array_fill_keys($labels, 0);

    $sql = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') as month_str, COUNT(*) as count
            FROM equipment
            WHERE company_id = ? AND purchase_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 MONTH), '%Y-%m-01') AND active = 1
            GROUP BY month_str";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($counts[$row['month_str']])) {
                $counts[$row['month_str']] = (int)$row['count'];
            }
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'labels' => array_keys($counts),
        'data' => array_values($counts)
    ];
}

/**
 * Assets by department
 */
function get_assets_by_department() {
    global $conn, $company_id;

    $labels = [];
    $data = [];
    $sql = "SELECT COALESCE(d.name, 'Unassigned') as dept_name, COUNT(*) as count
            FROM equipment e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY dept_name
            ORDER BY count DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['dept_name'];
            $data[] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return ['labels' => $labels, 'data' => $data];
}
