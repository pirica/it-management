<?php
/**
 * Functions for Visitors Access Log Module
 */

/**
 * Fetch all visitor logs for a company
 */
function val_fetch_logs($conn, $company_id, $search = '', $sort = 'date_time_in', $dir = 'DESC', $limit = 25, $offset = 0) {
    $company_id = (int)$company_id;
    $search = trim($search);
    $sort = itm_is_safe_identifier($sort) ? $sort : 'date_time_in';
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    $limit = (int)$limit;
    $offset = (int)$offset;

    $sql = "SELECT * FROM visitors_access_log WHERE company_id = ?";
    $params = [$company_id];
    $types = "i";

    if ($search !== '') {
        $sql .= " AND (visitor_name LIKE ? OR company_department LIKE ? OR reason_for_visit LIKE ? OR pre_approved_by LIKE ? OR room_opened_by LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, array_fill(0, 5, $searchParam));
        $types .= "sssss";
    }

    $sql .= " ORDER BY $sort $dir LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $logs;
}

/**
 * Count total visitor logs for pagination
 */
function val_count_logs($conn, $company_id, $search = '') {
    $company_id = (int)$company_id;
    $search = trim($search);

    $sql = "SELECT COUNT(*) as total FROM visitors_access_log WHERE company_id = ?";
    $params = [$company_id];
    $types = "i";

    if ($search !== '') {
        $sql .= " AND (visitor_name LIKE ? OR company_department LIKE ? OR reason_for_visit LIKE ? OR pre_approved_by LIKE ? OR room_opened_by LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, array_fill(0, 5, $searchParam));
        $types .= "sssss";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0);
}

/**
 * Check if a log entry is from today
 */
function val_is_today($dateTimeStr) {
    if (!$dateTimeStr) return false;
    $date = date('Y-m-d', strtotime($dateTimeStr));
    return $date === date('Y-m-d');
}

/**
 * Format date time for display
 */
function val_format_datetime($dateTimeStr) {
    if (!$dateTimeStr) return '—';
    return date('d/M H:i', strtotime($dateTimeStr));
}
