<?php
/**
 * Reports Hub Helpers
 * @file modules/reports/api/helpers.php
 */

/**
 * Equipment statistics by type
 * Map to: ['Servers', 'Workstations', 'Printers', 'Network Devices']
 */
function get_equipment_statistics() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0];
    $sql = "SELECT et.name, COUNT(*) as count
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY et.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'server') !== false) $stats[0] += $row['count'];
            elseif (strpos($name, 'workstation') !== false || strpos($name, 'laptop') !== false || strpos($name, 'desktop') !== false) $stats[1] += $row['count'];
            elseif (strpos($name, 'printer') !== false) $stats[2] += $row['count'];
            else $stats[3] += $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Ticket status statistics
 * Map to: ['Open', 'In Progress', 'Resolved', 'Closed']
 */
function get_ticket_statistics() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0];
    $sql = "SELECT ts.name, COUNT(*) as count
            FROM tickets t
            JOIN ticket_statuses ts ON t.status_id = ts.id
            WHERE t.company_id = ?
            GROUP BY ts.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'open') !== false) $stats[0] += $row['count'];
            elseif (strpos($name, 'progress') !== false) $stats[1] += $row['count'];
            elseif (strpos($name, 'resolved') !== false) $stats[2] += $row['count'];
            elseif (strpos($name, 'closed') !== false) $stats[3] += $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Employee department distribution
 * Map to: ['Engineering', 'IT', 'Sales', 'HR', 'Finance']
 */
function get_hr_statistics() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0, 0];
    $sql = "SELECT d.name, COUNT(*) as count
            FROM employees e
            JOIN departments d ON e.department_id = d.id
            WHERE e.company_id = ?
            GROUP BY d.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'eng') !== false) $stats[0] += $row['count'];
            elseif (strpos($name, 'it') !== false || strpos($name, 'tech') !== false) $stats[1] += $row['count'];
            elseif (strpos($name, 'sales') !== false) $stats[2] += $row['count'];
            elseif (strpos($name, 'hr') !== false || strpos($name, 'human') !== false) $stats[3] += $row['count'];
            elseif (strpos($name, 'fin') !== false) $stats[4] += $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Network device type counts
 * Map to: ['Servers', 'Switches', 'Routers', 'Firewalls', 'Access Points']
 */
function get_network_device_counts() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0, 0];
    $sql = "SELECT et.name, COUNT(*) as count
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY et.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'server') !== false) $stats[0] += $row['count'];
            elseif (strpos($name, 'switch') !== false) $stats[1] += $row['count'];
            elseif (strpos($name, 'router') !== false) $stats[2] += $row['count'];
            elseif (strpos($name, 'firewall') !== false) $stats[3] += $row['count'];
            elseif (strpos($name, 'access point') !== false || strpos($name, 'ap') !== false) $stats[4] += $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Budget distribution by category
 * Map to: ['Personnel', 'Equipment', 'Software', 'Services', 'Other']
 */
function get_budget_statistics() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0, 0];
    $sql = "SELECT bc.name, SUM(ab.amount) as total
            FROM annual_budgets ab
            JOIN gl_accounts gl ON ab.gl_account_id = gl.id
            JOIN budget_categories bc ON gl.category_id = bc.id
            WHERE ab.company_id = ?
            GROUP BY bc.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'personnel') !== false || strpos($name, 'staff') !== false) $stats[0] += $row['total'];
            elseif (strpos($name, 'equipment') !== false || strpos($name, 'hardware') !== false) $stats[1] += $row['total'];
            elseif (strpos($name, 'software') !== false || strpos($name, 'license') !== false) $stats[2] += $row['total'];
            elseif (strpos($name, 'service') !== false) $stats[3] += $row['total'];
            else $stats[4] += $row['total'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Equipment per location
 * Map to: ['Floor 1', 'Floor 2', 'Office A', 'Warehouse']
 */
function get_floorplan_location_data() {
    global $conn, $company_id;

    $stats = [0, 0, 0, 0];
    $sql = "SELECT l.name, COUNT(*) as count
            FROM equipment e
            JOIN it_locations l ON e.location_id = l.id
            WHERE e.company_id = ? AND e.active = 1
            GROUP BY l.name";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = strtolower($row['name']);
            if (strpos($name, 'floor 1') !== false) $stats[0] += $row['count'];
            elseif (strpos($name, 'floor 2') !== false) $stats[1] += $row['count'];
            elseif (strpos($name, 'office a') !== false) $stats[2] += $row['count'];
            elseif (strpos($name, 'warehouse') !== false) $stats[3] += $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * Inventory stock distribution
 * Map to: ['Low Stock', 'Normal', 'High']
 */
function get_inventory_stock_levels() {
    global $conn, $company_id;

    $stats = [0, 0, 0]; // Low, Normal, High
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
                $stats[0]++;
            } elseif ($on_hand > $min * 3) {
                $stats[2]++;
            } else {
                $stats[1]++;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}

/**
 * License statistics
 * Map to: ['Active', 'Expiring Soon', 'Expired']
 */
function get_license_statistics() {
    global $conn, $company_id;

    $stats = [0, 0, 0];
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
                $stats[0]++; // Active (no expiry)
                continue;
            }

            $expiry = new DateTime($row['expiry_date']);
            if ($expiry < $today) {
                $stats[2]++;
            } elseif ($expiry < $soon) {
                $stats[1]++;
            } else {
                $stats[0]++;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $stats;
}
