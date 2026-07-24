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
