<?php
/**
 * Smart dashboard widget SQL helpers — counts and 7-day sparkline series.
 *
 * Why: Centralize widget metrics so dashboard.php and verify scripts do not duplicate module SQL.
 */

if (!function_exists('itm_dashboard_queries_last_n_day_labels')) {
    /**
     * @return array{labels:array<int,string>,dates:array<int,string>}
     */
    function itm_dashboard_queries_last_n_day_labels($days = 7)
    {
        $days = max(1, (int)$days);
        $labels = [];
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $dates[] = $date;
            $labels[] = date('d/M', strtotime($date));
        }

        return ['labels' => $labels, 'dates' => $dates];
    }
}

if (!function_exists('itm_dashboard_query_it_department_employee')) {
    function itm_dashboard_query_it_department_employee($conn, $companyId, $employeeId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return false;
        }
        if (function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId)) {
            return true;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT d.name FROM employees e
             INNER JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
             WHERE e.id = ? AND e.company_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = function_exists('itm_mysqli_stmt_fetch_assoc')
            ? itm_mysqli_stmt_fetch_assoc($stmt)
            : null;
        if ($row === null && function_exists('mysqli_stmt_get_result')) {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
        }
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            return false;
        }

        return strtolower(trim((string)($row['name'] ?? ''))) === 'it';
    }
}

if (!function_exists('itm_dashboard_query_server_equipment_ids')) {
    /**
     * @return array<int,int>
     */
    function itm_dashboard_query_server_equipment_ids($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return [];
        }

        $sql = 'SELECT e.id
                FROM equipment e
                INNER JOIN equipment_types et ON et.id = e.equipment_type_id
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND e.active = 1
                  AND et.name = \'Server\'
                ORDER BY e.hostname ASC, e.id ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $ids = [];
        if (function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
            $rows = itm_mysqli_stmt_fetch_all_assoc($stmt);
            foreach ($rows as $row) {
                $ids[] = (int)($row['id'] ?? 0);
            }
        } else {
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $ids[] = (int)($row['id'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);

        return array_values(array_filter($ids, static function ($id) {
            return $id > 0;
        }));
    }
}

if (!function_exists('itm_dashboard_query_my_open_tickets_count')) {
    function itm_dashboard_query_my_open_tickets_count($conn, $companyId, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM tickets t
                INNER JOIN ticket_statuses ts ON ts.id = t.status_id
                WHERE t.company_id = ?
                  AND t.assigned_to_employee_id = ?
                  AND t.deleted_at IS NULL
                  AND t.is_archived = 0
                  AND ts.is_closed = 0';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $count = 0;
        if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
            $row = itm_mysqli_stmt_fetch_assoc($stmt);
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        } else {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);

        return $count;
    }
}

if (!function_exists('itm_dashboard_query_my_open_tickets_trend')) {
    /**
     * @return array{labels:array<int,string>,data:array<int,int>}
     */
    function itm_dashboard_query_my_open_tickets_trend($conn, $companyId, $employeeId, $days = 7)
    {
        $meta = itm_dashboard_queries_last_n_day_labels($days);
        $data = array_fill(0, count($meta['dates']), 0);
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
            return ['labels' => $meta['labels'], 'data' => $data];
        }

        $sql = 'SELECT DATE(t.created_at) AS day_key, COUNT(*) AS cnt
                FROM tickets t
                WHERE t.company_id = ?
                  AND t.assigned_to_employee_id = ?
                  AND t.deleted_at IS NULL
                  AND t.created_at >= ?
                GROUP BY day_key';
        $startDate = $meta['dates'][0] . ' 00:00:00';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iis', $companyId, $employeeId, $startDate);
            mysqli_stmt_execute($stmt);
            $byDay = [];
            if (function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
                foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
                    $byDay[(string)($row['day_key'] ?? '')] = (int)($row['cnt'] ?? 0);
                }
            } else {
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $byDay[(string)($row['day_key'] ?? '')] = (int)($row['cnt'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
            foreach ($meta['dates'] as $idx => $date) {
                $data[$idx] = (int)($byDay[$date] ?? 0);
            }
        }

        return ['labels' => $meta['labels'], 'data' => $data];
    }
}

if (!function_exists('itm_dashboard_query_expiring_within_days_count')) {
    function itm_dashboard_query_expiring_within_days_count($conn, $companyId, $days = 30)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM equipment e
                WHERE e.company_id = ?
                  AND e.deleted_at IS NULL
                  AND (
                    (e.certificate_expiry IS NOT NULL
                     AND e.certificate_expiry >= CURDATE()
                     AND e.certificate_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                    OR
                    (e.warranty_expiry IS NOT NULL
                     AND e.warranty_expiry >= CURDATE()
                     AND e.warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                  )';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $days, $days);
        mysqli_stmt_execute($stmt);
        $count = 0;
        if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
            $row = itm_mysqli_stmt_fetch_assoc($stmt);
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        } else {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);

        return $count;
    }
}

if (!function_exists('itm_dashboard_query_expiring_trend')) {
    /**
     * @return array{labels:array<int,string>,data:array<int,int>}
     */
    function itm_dashboard_query_expiring_trend($conn, $companyId, $days = 7)
    {
        $meta = itm_dashboard_queries_last_n_day_labels($days);
        $data = array_fill(0, count($meta['dates']), 0);
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return ['labels' => $meta['labels'], 'data' => $data];
        }

        foreach ($meta['dates'] as $idx => $date) {
            $sql = 'SELECT COUNT(*) AS cnt
                    FROM equipment e
                    WHERE e.company_id = ?
                      AND e.deleted_at IS NULL
                      AND (
                        e.certificate_expiry = ?
                        OR e.warranty_expiry = ?
                      )';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $date, $date);
            mysqli_stmt_execute($stmt);
            if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
                $row = itm_mysqli_stmt_fetch_assoc($stmt);
                if (is_array($row)) {
                    $data[$idx] = (int)($row['cnt'] ?? 0);
                }
            } else {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if (is_array($row)) {
                    $data[$idx] = (int)($row['cnt'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
        }

        return ['labels' => $meta['labels'], 'data' => $data];
    }
}

if (!function_exists('itm_dashboard_query_visitors_today_count')) {
    function itm_dashboard_query_visitors_today_count($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM visitors_access_log v
                WHERE v.company_id = ?
                  AND v.deleted_at IS NULL
                  AND (
                    DATE(v.date_time_in) = CURDATE()
                    OR (v.date_time_in IS NULL AND DATE(v.created_at) = CURDATE())
                  )';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $count = 0;
        if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
            $row = itm_mysqli_stmt_fetch_assoc($stmt);
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        } else {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);

        return $count;
    }
}

if (!function_exists('itm_dashboard_query_visitors_trend')) {
    /**
     * @return array{labels:array<int,string>,data:array<int,int>}
     */
    function itm_dashboard_query_visitors_trend($conn, $companyId, $days = 7)
    {
        $meta = itm_dashboard_queries_last_n_day_labels($days);
        $data = array_fill(0, count($meta['dates']), 0);
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return ['labels' => $meta['labels'], 'data' => $data];
        }

        $sql = 'SELECT DATE(COALESCE(v.date_time_in, v.created_at)) AS day_key, COUNT(*) AS cnt
                FROM visitors_access_log v
                WHERE v.company_id = ?
                  AND v.deleted_at IS NULL
                  AND COALESCE(v.date_time_in, v.created_at) >= ?
                GROUP BY day_key';
        $startDate = $meta['dates'][0] . ' 00:00:00';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $startDate);
            mysqli_stmt_execute($stmt);
            $byDay = [];
            if (function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
                foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
                    $byDay[(string)($row['day_key'] ?? '')] = (int)($row['cnt'] ?? 0);
                }
            } else {
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $byDay[(string)($row['day_key'] ?? '')] = (int)($row['cnt'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
            foreach ($meta['dates'] as $idx => $date) {
                $data[$idx] = (int)($byDay[$date] ?? 0);
            }
        }

        return ['labels' => $meta['labels'], 'data' => $data];
    }
}

if (!function_exists('itm_dashboard_query_backup_tape_gap_count_for_date')) {
    function itm_dashboard_query_backup_tape_gap_count_for_date($conn, $companyId, $logDate, array $serverIds)
    {
        $companyId = (int)$companyId;
        $logDate = trim((string)$logDate);
        if (!($conn instanceof mysqli) || $companyId <= 0 || $logDate === '' || $serverIds === []) {
            return 0;
        }

        $gaps = 0;
        foreach ($serverIds as $serverId) {
            $serverId = (int)$serverId;
            if ($serverId <= 0) {
                continue;
            }
            $stmt = mysqli_prepare(
                $conn,
                'SELECT backup_status FROM backup_tape_log
                 WHERE company_id = ? AND server_id = ? AND log_date = ?
                 LIMIT 1'
            );
            if (!$stmt) {
                $gaps++;
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'iis', $companyId, $serverId, $logDate);
            mysqli_stmt_execute($stmt);
            $row = function_exists('itm_mysqli_stmt_fetch_assoc')
                ? itm_mysqli_stmt_fetch_assoc($stmt)
                : null;
            if ($row === null && function_exists('mysqli_stmt_get_result')) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
            }
            mysqli_stmt_close($stmt);
            if (!is_array($row)) {
                $gaps++;
                continue;
            }
            $status = trim((string)($row['backup_status'] ?? ''));
            if (strcasecmp($status, 'Fail') === 0) {
                $gaps++;
            }
        }

        return $gaps;
    }
}

if (!function_exists('itm_dashboard_query_backup_tape_gaps_mtd')) {
    function itm_dashboard_query_backup_tape_gaps_mtd($conn, $companyId, $year = 0, $month = 0)
    {
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return 0;
        }
        $year = $year > 0 ? (int)$year : (int)date('Y');
        $month = $month > 0 ? (int)$month : (int)date('n');
        $serverIds = itm_dashboard_query_server_equipment_ids($conn, $companyId);
        if ($serverIds === []) {
            return 0;
        }

        $today = date('Y-m-d');
        $maxDay = (int)date('j');
        if ((int)date('Y') !== $year || (int)date('n') !== $month) {
            $maxDay = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        }

        $total = 0;
        for ($day = 1; $day <= $maxDay; $day++) {
            $logDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ($logDate > $today) {
                break;
            }
            $total += itm_dashboard_query_backup_tape_gap_count_for_date($conn, $companyId, $logDate, $serverIds);
        }

        return $total;
    }
}

if (!function_exists('itm_dashboard_query_backup_tape_gaps_trend')) {
    /**
     * @return array{labels:array<int,string>,data:array<int,int>}
     */
    function itm_dashboard_query_backup_tape_gaps_trend($conn, $companyId, $days = 7)
    {
        $meta = itm_dashboard_queries_last_n_day_labels($days);
        $data = [];
        $companyId = (int)$companyId;
        $serverIds = itm_dashboard_query_server_equipment_ids($conn, $companyId);
        foreach ($meta['dates'] as $date) {
            $data[] = $serverIds === []
                ? 0
                : itm_dashboard_query_backup_tape_gap_count_for_date($conn, $companyId, $date, $serverIds);
        }

        return ['labels' => $meta['labels'], 'data' => $data];
    }
}
