<?php
if (!function_exists('itm_ticket_merge_tickets')) {
    function itm_ticket_merge_tickets($conn, $companyId, $sourceTicketId, $targetTicketId, $actorEmployeeId) {
        if (!$conn instanceof mysqli) return ['ok' => false, 'error' => 'Database unavailable.'];
        $companyId = (int)$companyId; $sourceTicketId = (int)$sourceTicketId; $targetTicketId = (int)$targetTicketId; $actorEmployeeId = (int)$actorEmployeeId;
        if ($companyId <= 0 || $sourceTicketId <= 0 || $targetTicketId <= 0 || $sourceTicketId === $targetTicketId) {
            return ['ok' => false, 'error' => 'Invalid ticket selection.'];
        }
        $sql = 'SELECT id, merged_into_ticket_id, deleted_at FROM tickets WHERE id = ? AND company_id = ? LIMIT 1';
        foreach ([$sourceTicketId => 'source', $targetTicketId => 'target'] as $tid => $label) {
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) return ['ok' => false, 'error' => 'Failed to load tickets.'];
            mysqli_stmt_bind_param($stmt, 'ii', $tid, $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!is_array($row) || !empty($row['deleted_at']) || !empty($row['merged_into_ticket_id'])) {
                return ['ok' => false, 'error' => ucfirst($label) . ' ticket is not available for merge.'];
            }
        }
        mysqli_begin_transaction($conn);
        $failed = false;
        $st = mysqli_prepare($conn, 'UPDATE ticket_comments SET ticket_id = ? WHERE company_id = ? AND ticket_id = ?');
        if ($st) { mysqli_stmt_bind_param($st, 'iii', $targetTicketId, $companyId, $sourceTicketId); if (!mysqli_stmt_execute($st)) $failed = true; mysqli_stmt_close($st); } else $failed = true;
        $st = mysqli_prepare($conn, 'UPDATE ticket_activity SET ticket_id = ? WHERE company_id = ? AND ticket_id = ?');
        if ($st) { mysqli_stmt_bind_param($st, 'iii', $targetTicketId, $companyId, $sourceTicketId); if (!mysqli_stmt_execute($st)) $failed = true; mysqli_stmt_close($st); } else $failed = true;
        $st = mysqli_prepare($conn, 'UPDATE tickets SET merged_into_ticket_id = ?, is_archived = 1, active = 0, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if ($st) {
            mysqli_stmt_bind_param($st, 'iiiii', $targetTicketId, $actorEmployeeId, $actorEmployeeId, $sourceTicketId, $companyId);
            if (!mysqli_stmt_execute($st) || mysqli_stmt_affected_rows($st) !== 1) $failed = true;
            mysqli_stmt_close($st);
        } else $failed = true;
        if ($failed) { mysqli_rollback($conn); return ['ok' => false, 'error' => 'Merge failed — no changes saved.']; }
        if (function_exists('itm_ticket_activity_log')) {
            itm_ticket_activity_log($conn, $companyId, $targetTicketId, $actorEmployeeId, 'ticket_merged', ['source_ticket_id' => $sourceTicketId]);
            itm_ticket_activity_log($conn, $companyId, $sourceTicketId, $actorEmployeeId, 'ticket_merged_into', ['target_ticket_id' => $targetTicketId]);
        }
        mysqli_commit($conn);
        return ['ok' => true, 'source_ticket_id' => $sourceTicketId, 'target_ticket_id' => $targetTicketId];
    }
}
