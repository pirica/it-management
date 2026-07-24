<?php
/**
 * Minimal ticket creation for Live Agent flow.
 */

if (!function_exists('itm_live_chat_create_ticket')) {
    function itm_live_chat_create_ticket($conn, $companyId, $requesterEmployeeId, $title, $description = '', $priorityId = null)
    {
        $companyId = (int)$companyId;
        $requesterEmployeeId = (int)$requesterEmployeeId;
        $title = trim((string)$title);
        if ($companyId <= 0 || $requesterEmployeeId <= 0 || $title === '') {
            return false;
        }

        if ($priorityId === null || (int)$priorityId <= 0) {
            $sqlPri = 'SELECT id FROM ticket_priorities WHERE company_id = ? AND active = 1 ORDER BY level ASC LIMIT 1';
            $stmtPri = mysqli_prepare($conn, $sqlPri);
            if ($stmtPri) {
                mysqli_stmt_bind_param($stmtPri, 'i', $companyId);
                mysqli_stmt_execute($stmtPri);
                $resPri = mysqli_stmt_get_result($stmtPri);
                $priRow = $resPri ? mysqli_fetch_assoc($resPri) : null;
                mysqli_stmt_close($stmtPri);
                $priorityId = $priRow ? (int)$priRow['id'] : null;
            }
        } else {
            $priorityId = (int)$priorityId;
        }

        $statusId = null;
        $sqlSt = 'SELECT id FROM ticket_statuses WHERE company_id = ? AND active = 1 ORDER BY id ASC LIMIT 1';
        $stmtSt = mysqli_prepare($conn, $sqlSt);
        if ($stmtSt) {
            mysqli_stmt_bind_param($stmtSt, 'i', $companyId);
            mysqli_stmt_execute($stmtSt);
            $resSt = mysqli_stmt_get_result($stmtSt);
            $stRow = $resSt ? mysqli_fetch_assoc($resSt) : null;
            mysqli_stmt_close($stmtSt);
            $statusId = $stRow ? (int)$stRow['id'] : null;
        }

        $sql = 'INSERT INTO tickets (company_id, title, description, status_id, priority_id, created_by_employee_id, active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $description = (string)$description;
        $createdBy = (int)($_SESSION['employee_id'] ?? $requesterEmployeeId);
        mysqli_stmt_bind_param($stmt, 'issiiii', $companyId, $title, $description, $statusId, $priorityId, $requesterEmployeeId, $createdBy);
        $ok = mysqli_stmt_execute($stmt);
        $ticketId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if ($ok && $ticketId > 0 && $priorityId) {
            itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId);
        }
        return $ok ? $ticketId : false;
    }
}

if (!function_exists('itm_live_chat_validate_ticket_for_requester')) {
    function itm_live_chat_validate_ticket_for_requester($conn, $companyId, $ticketId, $requesterEmployeeId, $isSupportAgent)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $requesterEmployeeId = (int)$requesterEmployeeId;
        $sql = 'SELECT id, created_by_employee_id, assigned_to_employee_id FROM tickets
                WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return null;
        }
        if (!$isSupportAgent && (int)$row['created_by_employee_id'] !== $requesterEmployeeId) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('itm_live_chat_reopen_ticket')) {
    /**
     * Move a closed ticket back to an open status and refresh SLA due dates.
     *
     * @return array<string,mixed>|false Ticket row on success, false on failure.
     */
    function itm_live_chat_reopen_ticket($conn, $companyId, $ticketId, $actorEmployeeId, $isSupportAgent)
    {
        $ticket = itm_live_chat_validate_ticket_for_requester($conn, $companyId, $ticketId, $actorEmployeeId, $isSupportAgent);
        if (!$ticket) {
            return false;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = (int)$actorEmployeeId;

        $sqlClosed = 'SELECT t.id, t.priority_id, ts.is_closed
                      FROM tickets t
                      INNER JOIN ticket_statuses ts ON ts.id = t.status_id AND ts.company_id = t.company_id
                      WHERE t.id = ? AND t.company_id = ? AND t.deleted_at IS NULL AND t.active = 1
                      LIMIT 1';
        $stmtClosed = mysqli_prepare($conn, $sqlClosed);
        if (!$stmtClosed) {
            return false;
        }
        mysqli_stmt_bind_param($stmtClosed, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmtClosed);
        $resClosed = mysqli_stmt_get_result($stmtClosed);
        $closedRow = $resClosed ? mysqli_fetch_assoc($resClosed) : null;
        mysqli_stmt_close($stmtClosed);
        if (!$closedRow || (int)($closedRow['is_closed'] ?? 0) !== 1) {
            return false;
        }

        $openStatusId = null;
        $sqlOpen = 'SELECT id FROM ticket_statuses
                    WHERE company_id = ? AND active = 1 AND is_closed = 0
                    ORDER BY CASE WHEN LOWER(name) = \'open\' THEN 0 WHEN LOWER(name) = \'in progress\' THEN 1 ELSE 2 END, id ASC
                    LIMIT 1';
        $stmtOpen = mysqli_prepare($conn, $sqlOpen);
        if ($stmtOpen) {
            mysqli_stmt_bind_param($stmtOpen, 'i', $companyId);
            mysqli_stmt_execute($stmtOpen);
            $resOpen = mysqli_stmt_get_result($stmtOpen);
            $openRow = $resOpen ? mysqli_fetch_assoc($resOpen) : null;
            mysqli_stmt_close($stmtOpen);
            $openStatusId = $openRow ? (int)$openRow['id'] : null;
        }
        if ($openStatusId === null || $openStatusId <= 0) {
            return false;
        }

        $sqlUp = 'UPDATE tickets SET status_id = ?, resolved_at = NULL, updated_by = ? WHERE id = ? AND company_id = ?';
        $stmtUp = mysqli_prepare($conn, $sqlUp);
        if (!$stmtUp) {
            return false;
        }
        mysqli_stmt_bind_param($stmtUp, 'iiii', $openStatusId, $actorEmployeeId, $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmtUp);
        mysqli_stmt_close($stmtUp);
        if (!$ok) {
            return false;
        }

        $priorityId = (int)($closedRow['priority_id'] ?? 0);
        if ($priorityId > 0) {
            itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId);
        }
        itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'ticket_reopened', [
            'status_id' => $openStatusId,
            'reopened_by_employee_id' => $actorEmployeeId,
        ]);

        return ['id' => $ticketId, 'status_id' => $openStatusId, 'priority_id' => $priorityId];
    }
}
