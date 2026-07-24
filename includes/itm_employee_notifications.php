<?php
/**
 * Per-employee in-app notification inbox.
 */

if (!function_exists('itm_employee_notification_create')) {
    function itm_employee_notification_create($conn, $companyId, $employeeId, $moduleSlug, $recordId, $title, $body, $actionUrl)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $recordId = $recordId !== null ? (int)$recordId : null;
        if ($companyId <= 0 || $employeeId <= 0) {
            return false;
        }

        $sql = 'INSERT INTO employee_notifications (company_id, employee_id, module_slug, record_id, title, body, action_url, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $moduleSlug = trim((string)$moduleSlug);
        $title = trim((string)$title);
        $body = $body !== null ? (string)$body : null;
        $actionUrl = $actionUrl !== null ? (string)$actionUrl : null;
        $createdBy = (int)($_SESSION['employee_id'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'iisisssi', $companyId, $employeeId, $moduleSlug, $recordId, $title, $body, $actionUrl, $createdBy);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_employee_notification_mark_read')) {
    function itm_employee_notification_mark_read($conn, $companyId, $employeeId, $notificationId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $notificationId = (int)$notificationId;
        if ($companyId <= 0 || $employeeId <= 0 || $notificationId <= 0) {
            return false;
        }
        $sql = 'UPDATE employee_notifications SET is_read = 1, read_at = NOW(), updated_by = ?
                WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $updatedBy = (int)($_SESSION['employee_id'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'iiii', $updatedBy, $notificationId, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_employee_notification_unread_count')) {
    function itm_employee_notification_unread_count($conn, $companyId, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS c FROM employee_notifications
                WHERE company_id = ? AND employee_id = ? AND is_read = 0 AND deleted_at IS NULL AND active = 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_employee_notifications_list_recent')) {
    function itm_employee_notifications_list_recent($conn, $companyId, $employeeId, $limit = 20)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $limit = max(1, min(50, (int)$limit));
        $rows = [];
        $sql = 'SELECT id, module_slug, record_id, title, body, action_url, is_read, created_at
                FROM employee_notifications
                WHERE company_id = ? AND employee_id = ? AND deleted_at IS NULL AND active = 1
                ORDER BY is_read ASC, created_at DESC
                LIMIT ' . $limit;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
