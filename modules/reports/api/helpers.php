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
