<?php
/**
 * Ticket SLA policy lookup and timestamp stamps.
 */

if (!function_exists('itm_ticket_sla_policy_for_priority')) {
    function itm_ticket_sla_policy_for_priority($conn, $companyId, $priorityId)
    {
        $companyId = (int)$companyId;
        $priorityId = (int)$priorityId;
        if ($companyId <= 0 || $priorityId <= 0) {
            return null;
        }
        $sql = 'SELECT response_minutes, resolve_minutes FROM ticket_sla_policies
                WHERE company_id = ? AND priority_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $priorityId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_ticket_sla_apply_on_create')) {
    function itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId, $createdAt = null)
    {
        $policy = itm_ticket_sla_policy_for_priority($conn, $companyId, $priorityId);
        if (!$policy) {
            return false;
        }
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $base = $createdAt ? strtotime((string)$createdAt) : time();
        if ($base === false) {
            $base = time();
        }
        $responseDue = date('Y-m-d H:i:s', $base + ((int)$policy['response_minutes'] * 60));
        $resolveDue = date('Y-m-d H:i:s', $base + ((int)$policy['resolve_minutes'] * 60));
        $sql = 'UPDATE tickets SET sla_response_due_at = ?, sla_resolve_due_at = ?
                WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ssii', $responseDue, $resolveDue, $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_stamp_first_response')) {
    function itm_ticket_sla_stamp_first_response($conn, $ticketId, $companyId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'UPDATE tickets SET first_response_at = NOW()
                WHERE id = ? AND company_id = ? AND first_response_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_stamp_resolved')) {
    function itm_ticket_sla_stamp_resolved($conn, $ticketId, $companyId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'UPDATE tickets SET resolved_at = NOW()
                WHERE id = ? AND company_id = ? AND resolved_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_check_breaches')) {
    function itm_ticket_sla_check_breaches($conn, $companyId, $ticketId, $actorEmployeeId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'SELECT first_response_at, resolved_at, sla_response_due_at, sla_resolve_due_at
                FROM tickets WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return;
        }
        $now = time();
        if ($row['first_response_at'] === null && !empty($row['sla_response_due_at']) && strtotime($row['sla_response_due_at']) < $now) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'sla_response_breached', [
                'sla_response_due_at' => $row['sla_response_due_at'],
            ]);
        }
        if ($row['resolved_at'] === null && !empty($row['sla_resolve_due_at']) && strtotime($row['sla_resolve_due_at']) < $now) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'sla_resolve_breached', [
                'sla_resolve_due_at' => $row['sla_resolve_due_at'],
            ]);
        }
    }
}
