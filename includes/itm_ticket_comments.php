<?php
/**
 * Ticket threaded comments (separate from live chat bubbles).
 */

if (!function_exists('itm_ticket_comment_body_preview')) {
    function itm_ticket_comment_body_preview($body, $maxLen = 120)
    {
        $body = trim(preg_replace('/\s+/', ' ', (string)$body));
        if (strlen($body) <= $maxLen) {
            return $body;
        }
        return substr($body, 0, $maxLen - 1) . '…';
    }
}

if (!function_exists('itm_ticket_comment_create')) {
    function itm_ticket_comment_create($conn, $companyId, $ticketId, $employeeId, $body, $isInternal = 0)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $employeeId = (int)$employeeId;
        $body = trim((string)$body);
        $isInternal = (int)((bool)$isInternal);
        if ($companyId <= 0 || $ticketId <= 0 || $employeeId <= 0 || $body === '') {
            return false;
        }

        $sql = 'INSERT INTO ticket_comments (company_id, ticket_id, employee_id, body, is_internal, created_by)
                VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $createdBy = (int)($_SESSION['employee_id'] ?? $employeeId);
        mysqli_stmt_bind_param($stmt, 'iiisii', $companyId, $ticketId, $employeeId, $body, $isInternal, $createdBy);
        $ok = mysqli_stmt_execute($stmt);
        $commentId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if ($ok && $commentId > 0) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $employeeId, 'comment_added', [
                'comment_id' => $commentId,
                'is_internal' => $isInternal,
                'body_preview' => itm_ticket_comment_body_preview($body),
                'source' => 'ticket_comments',
            ]);
        }
        return $ok ? $commentId : false;
    }
}

if (!function_exists('itm_ticket_comments_for_ticket')) {
    function itm_ticket_comments_for_ticket($conn, $companyId, $ticketId, $viewerEmployeeId, $viewerIsSupportAgent)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $rows = [];
        $sql = 'SELECT tc.*, e.first_name, e.last_name, e.username
                FROM ticket_comments tc
                LEFT JOIN employees e ON e.id = tc.employee_id
                WHERE tc.company_id = ? AND tc.ticket_id = ? AND tc.deleted_at IS NULL AND tc.active = 1';
        if (!$viewerIsSupportAgent) {
            $sql .= ' AND tc.is_internal = 0';
        }
        $sql .= ' ORDER BY tc.created_at ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
