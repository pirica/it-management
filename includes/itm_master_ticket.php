<?php
/**
 * Cross-company master ticket rollup — global master_tickets (no company_id), sync to linked incidents, history log.
 */

if (!function_exists('itm_master_ticket_local_notes_marker')) {
    function itm_master_ticket_local_notes_marker()
    {
        return '--- Local incident notes ---';
    }
}

if (!function_exists('itm_master_ticket_block_marker')) {
    function itm_master_ticket_block_marker($masterTicketId)
    {
        return '--- Master ticket #' . (int)$masterTicketId . ' ---';
    }
}

if (!function_exists('itm_master_ticket_encode_json')) {
    function itm_master_ticket_encode_json($data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}

if (!function_exists('itm_master_ticket_allowed_company_ids')) {
    function itm_master_ticket_allowed_company_ids($conn, $employeeId, $isAdmin = null)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return [];
        }
        if ($isAdmin === null && function_exists('itm_is_admin')) {
            $isAdmin = itm_is_admin($conn, $employeeId);
        }
        if (!empty($isAdmin)) {
            $ids = [];
            $res = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1 ORDER BY id ASC');
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $ids[] = (int)$row['id'];
            }
            return $ids;
        }
        if (!function_exists('itm_employee_has_company_access')) {
            require_once ROOT_PATH . 'includes/itm_company_session.php';
        }
        $homeStmt = mysqli_prepare($conn, 'SELECT company_id FROM employees WHERE id = ? LIMIT 1');
        $homeCompanyId = 0;
        if ($homeStmt) {
            mysqli_stmt_bind_param($homeStmt, 'i', $employeeId);
            mysqli_stmt_execute($homeStmt);
            $homeRes = mysqli_stmt_get_result($homeStmt);
            $homeRow = $homeRes ? mysqli_fetch_assoc($homeRes) : null;
            mysqli_stmt_close($homeStmt);
            $homeCompanyId = (int)($homeRow['company_id'] ?? 0);
        }
        $allowed = [];
        if ($homeCompanyId > 0 && itm_employee_has_company_access($conn, $employeeId, $homeCompanyId, $isAdmin)) {
            $allowed[$homeCompanyId] = true;
        }
        $grantStmt = mysqli_prepare(
            $conn,
            'SELECT company_id FROM employee_companies WHERE employee_id = ? AND active = 1'
        );
        if ($grantStmt) {
            mysqli_stmt_bind_param($grantStmt, 'i', $employeeId);
            mysqli_stmt_execute($grantStmt);
            $grantRes = mysqli_stmt_get_result($grantStmt);
            while ($grantRes && ($gRow = mysqli_fetch_assoc($grantRes))) {
                $cid = (int)($gRow['company_id'] ?? 0);
                if ($cid > 0 && itm_employee_has_company_access($conn, $employeeId, $cid, $isAdmin)) {
                    $allowed[$cid] = true;
                }
            }
            mysqli_stmt_close($grantStmt);
        }
        return array_keys($allowed);
    }
}

if (!function_exists('itm_master_ticket_fetch_row')) {
    function itm_master_ticket_fetch_row($conn, $masterTicketId)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $masterTicketId = (int)$masterTicketId;
        if ($masterTicketId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM master_tickets WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $masterTicketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_master_ticket_log_history')) {
    function itm_master_ticket_log_history($conn, $masterTicketId, $eventType, $actorEmployeeId, $actorCompanyId, $summary, $changesJson, $metaJson)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $masterTicketId = (int)$masterTicketId;
        $eventType = trim((string)$eventType);
        if ($masterTicketId <= 0 || $eventType === '') {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO master_ticket_updates (master_ticket_id, event_type, summary, changes_json, meta_json, actor_employee_id, actor_company_id)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0))'
        );
        if (!$stmt) {
            return false;
        }
        $summary = $summary !== null ? (string)$summary : null;
        $changesJson = $changesJson !== null ? (string)$changesJson : null;
        $metaJson = $metaJson !== null ? (string)$metaJson : null;
        $actorEmployeeId = (int)$actorEmployeeId;
        $actorCompanyId = (int)$actorCompanyId;
        mysqli_stmt_bind_param(
            $stmt,
            'issssii',
            $masterTicketId,
            $eventType,
            $summary,
            $changesJson,
            $metaJson,
            $actorEmployeeId,
            $actorCompanyId
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_master_ticket_list_history')) {
    function itm_master_ticket_list_history($conn, $masterTicketId, $limit = 50)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $masterTicketId = (int)$masterTicketId;
        $limit = max(1, min(100, (int)$limit));
        if ($masterTicketId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.*, CONCAT(COALESCE(e.first_name, ""), " ", COALESCE(e.last_name, "")) AS actor_name, e.username AS actor_username
             FROM master_ticket_updates u
             LEFT JOIN employees e ON e.id = u.actor_employee_id
             WHERE u.master_ticket_id = ?
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT ' . (int)$limit
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $masterTicketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_master_ticket_format_history_summary')) {
    function itm_master_ticket_format_history_summary(array $historyRow)
    {
        $summary = trim((string)($historyRow['summary'] ?? ''));
        $eventType = (string)($historyRow['event_type'] ?? '');
        if ($eventType !== 'broadcast_to_tickets') {
            return $summary;
        }
        $messageText = '';
        $metaRaw = (string)($historyRow['meta_json'] ?? '');
        if ($metaRaw !== '') {
            $meta = json_decode($metaRaw, true);
            if (is_array($meta)) {
                if (!empty($meta['message'])) {
                    $messageText = trim((string)$meta['message']);
                } elseif (!empty($meta['message_preview'])) {
                    $messageText = trim((string)$meta['message_preview']);
                }
            }
        }
        if ($messageText === '') {
            return $summary;
        }
        if ($summary !== '' && strpos($summary, $messageText) !== false) {
            return $summary;
        }
        return ($summary !== '' ? $summary . ' — ' : '') . '"' . $messageText . '"';
    }
}

if (!function_exists('itm_master_ticket_list_broadcast_history')) {
    function itm_master_ticket_list_broadcast_history($conn, $masterTicketId, $limit = 20)
    {
        $rows = itm_master_ticket_list_history($conn, $masterTicketId, max(1, min(100, (int)$limit)));
        $broadcasts = [];
        foreach ($rows as $row) {
            if ((string)($row['event_type'] ?? '') === 'broadcast_to_tickets') {
                $broadcasts[] = $row;
            }
        }
        return $broadcasts;
    }
}

if (!function_exists('itm_master_ticket_fetch_history_row')) {
    function itm_master_ticket_fetch_history_row($conn, $masterTicketId, $historyId)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $masterTicketId = (int)$masterTicketId;
        $historyId = (int)$historyId;
        if ($masterTicketId <= 0 || $historyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.*, CONCAT(COALESCE(e.first_name, ""), " ", COALESCE(e.last_name, "")) AS actor_name, e.username AS actor_username
             FROM master_ticket_updates u
             LEFT JOIN employees e ON e.id = u.actor_employee_id
             WHERE u.id = ? AND u.master_ticket_id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $historyId, $masterTicketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_master_ticket_history_message_text')) {
    function itm_master_ticket_history_message_text(array $historyRow)
    {
        $metaRaw = (string)($historyRow['meta_json'] ?? '');
        if ($metaRaw !== '') {
            $meta = json_decode($metaRaw, true);
            if (is_array($meta)) {
                if (!empty($meta['message'])) {
                    return trim((string)$meta['message']);
                }
                if (!empty($meta['message_preview'])) {
                    return trim((string)$meta['message_preview']);
                }
            }
        }
        return itm_master_ticket_format_history_summary($historyRow);
    }
}

if (!function_exists('itm_master_ticket_hard_unlink_incident')) {
    function itm_master_ticket_hard_unlink_incident($conn, $masterTicketId, $companyId, $problemId, $ticketId, $actorEmployeeId, $sessionCompanyId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $sessionCompanyId = (int)$sessionCompanyId;
        if ($masterTicketId <= 0 || $companyId <= 0 || $problemId <= 0 || $ticketId <= 0) {
            return ['ok' => false, 'error' => 'Invalid incident reference.'];
        }
        if (!itm_master_ticket_fetch_row($conn, $masterTicketId)) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $actorEmployeeId)) {
            return ['ok' => false, 'error' => 'You cannot remove incidents from this master ticket.'];
        }
        $problem = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$problem || (int)($problem['master_ticket_id'] ?? 0) !== $masterTicketId) {
            return ['ok' => false, 'error' => 'Incident problem is not linked to this master ticket.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'DELETE FROM problem_ticket_links WHERE company_id = ? AND problem_id = ? AND ticket_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not remove incident link.'];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $problemId, $ticketId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Incident link not found.'];
        }
        if (function_exists('itm_ticket_activity_log')) {
            require_once ROOT_PATH . 'includes/itm_ticket_activity.php';
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'problem_unlinked', [
                'problem_id' => $problemId,
                'master_ticket_id' => $masterTicketId,
                'hard_delete' => true,
            ]);
        }
        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'incident_unlinked',
            $actorEmployeeId,
            $sessionCompanyId,
            'Hard-deleted incident link for ticket #' . $ticketId . ' (company ' . $companyId . ', problem #' . $problemId . ').',
            null,
            itm_master_ticket_encode_json([
                'company_id' => $companyId,
                'problem_id' => $problemId,
                'ticket_id' => $ticketId,
            ])
        );
        itm_master_ticket_refresh_summary($conn, $masterTicketId);
        return ['ok' => true];
    }
}

if (!function_exists('itm_master_ticket_hard_detach_problem')) {
    function itm_master_ticket_hard_detach_problem($conn, $masterTicketId, $companyId, $problemId, $actorEmployeeId, $sessionCompanyId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $sessionCompanyId = (int)$sessionCompanyId;
        if ($masterTicketId <= 0 || $companyId <= 0 || $problemId <= 0) {
            return ['ok' => false, 'error' => 'Invalid problem reference.'];
        }
        if (!itm_master_ticket_fetch_row($conn, $masterTicketId)) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $actorEmployeeId)) {
            return ['ok' => false, 'error' => 'You cannot remove problems from this master ticket.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET master_ticket_id = NULL, updated_by = ? WHERE id = ? AND company_id = ? AND master_ticket_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not detach problem.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $actorEmployeeId, $problemId, $companyId, $masterTicketId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Problem is not linked to this master ticket.'];
        }
        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'problem_detached',
            $actorEmployeeId,
            $sessionCompanyId,
            'Detached problem #' . $problemId . ' (company ' . $companyId . ') from master ticket.',
            null,
            itm_master_ticket_encode_json([
                'company_id' => $companyId,
                'problem_id' => $problemId,
            ])
        );
        itm_master_ticket_refresh_summary($conn, $masterTicketId);
        return ['ok' => true];
    }
}

if (!function_exists('itm_master_ticket_hard_delete_broadcast_comments')) {
    function itm_master_ticket_hard_delete_broadcast_comments($conn, $masterTicketId, array $meta)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $masterTicketId = (int)$masterTicketId;
        $deletedComments = 0;
        if (!empty($meta['comments']) && is_array($meta['comments'])) {
            $delComment = mysqli_prepare($conn, 'DELETE FROM ticket_comments WHERE id = ? AND company_id = ? AND ticket_id = ? LIMIT 1');
            if ($delComment) {
                foreach ($meta['comments'] as $commentRef) {
                    $commentId = (int)($commentRef['comment_id'] ?? 0);
                    $commentCompanyId = (int)($commentRef['company_id'] ?? 0);
                    $commentTicketId = (int)($commentRef['ticket_id'] ?? 0);
                    if ($commentId <= 0 || $commentCompanyId <= 0 || $commentTicketId <= 0) {
                        continue;
                    }
                    mysqli_stmt_bind_param($delComment, 'iii', $commentId, $commentCompanyId, $commentTicketId);
                    if (mysqli_stmt_execute($delComment) && mysqli_stmt_affected_rows($delComment) === 1) {
                        $deletedComments++;
                    }
                }
                mysqli_stmt_close($delComment);
            }
            return $deletedComments;
        }
        $message = trim((string)($meta['message'] ?? $meta['message_preview'] ?? ''));
        $ticketIds = array_values(array_filter(array_map('intval', (array)($meta['ticket_ids'] ?? []))));
        if ($message === '' || $ticketIds === []) {
            return 0;
        }
        $body = itm_master_ticket_block_marker($masterTicketId) . "\n" . $message;
        $delByBody = mysqli_prepare($conn, 'DELETE FROM ticket_comments WHERE company_id = ? AND ticket_id = ? AND body = ? LIMIT 1');
        if (!$delByBody) {
            return 0;
        }
        $incidents = itm_master_ticket_list_all_incidents($conn, $masterTicketId, null);
        foreach ($incidents as $incident) {
            $ticketId = (int)($incident['id'] ?? 0);
            $companyId = (int)($incident['company_id'] ?? 0);
            if ($ticketId <= 0 || $companyId <= 0 || !in_array($ticketId, $ticketIds, true)) {
                continue;
            }
            mysqli_stmt_bind_param($delByBody, 'iis', $companyId, $ticketId, $body);
            if (mysqli_stmt_execute($delByBody) && mysqli_stmt_affected_rows($delByBody) === 1) {
                $deletedComments++;
            }
        }
        mysqli_stmt_close($delByBody);
        return $deletedComments;
    }
}

if (!function_exists('itm_master_ticket_hard_delete_history_row')) {
    function itm_master_ticket_hard_delete_history_row($conn, $masterTicketId, $historyId, $actorEmployeeId, $sessionCompanyId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $historyId = (int)$historyId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $sessionCompanyId = (int)$sessionCompanyId;
        if ($masterTicketId <= 0 || $historyId <= 0) {
            return ['ok' => false, 'error' => 'Invalid history reference.'];
        }
        if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $actorEmployeeId)) {
            return ['ok' => false, 'error' => 'You cannot delete history for this master ticket.'];
        }
        $row = itm_master_ticket_fetch_history_row($conn, $masterTicketId, $historyId);
        if (!$row) {
            return ['ok' => false, 'error' => 'History row not found.'];
        }
        $deletedComments = 0;
        if ((string)($row['event_type'] ?? '') === 'broadcast_to_tickets') {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            if (is_array($meta)) {
                $deletedComments = itm_master_ticket_hard_delete_broadcast_comments($conn, $masterTicketId, $meta);
            }
        }
        $stmt = mysqli_prepare($conn, 'DELETE FROM master_ticket_updates WHERE id = ? AND master_ticket_id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not delete history row.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $historyId, $masterTicketId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'History row not found.'];
        }
        return ['ok' => true, 'deleted_comments' => $deletedComments];
    }
}

if (!function_exists('itm_master_ticket_build_canonical_body')) {
    function itm_master_ticket_build_canonical_body(array $masterRow)
    {
        $masterId = (int)($masterRow['id'] ?? 0);
        $parts = [itm_master_ticket_block_marker($masterId)];
        $description = trim((string)($masterRow['description'] ?? ''));
        if ($description !== '') {
            $parts[] = $description;
        }
        $rootCause = trim((string)($masterRow['root_cause'] ?? ''));
        if ($rootCause !== '') {
            $parts[] = 'Root cause: ' . $rootCause;
        }
        return implode("\n\n", $parts);
    }
}

if (!function_exists('itm_master_ticket_build_ticket_description')) {
    function itm_master_ticket_build_ticket_description(array $masterRow, $existingDescription)
    {
        $canonical = itm_master_ticket_build_canonical_body($masterRow);
        $existing = (string)$existingDescription;
        $marker = itm_master_ticket_local_notes_marker();
        $localNotes = '';
        $pos = strpos($existing, $marker);
        if ($pos !== false) {
            $localNotes = trim(substr($existing, $pos + strlen($marker)));
        } elseif (strpos($existing, itm_master_ticket_block_marker((int)($masterRow['id'] ?? 0))) === false && trim($existing) !== '') {
            $localNotes = trim($existing);
        }
        if ($localNotes !== '') {
            return $canonical . "\n\n" . $marker . "\n" . $localNotes;
        }
        return $canonical;
    }
}

if (!function_exists('itm_master_ticket_list_all_incidents')) {
    function itm_master_ticket_list_all_incidents($conn, $masterTicketId, array $allowedCompanyIds = null)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $masterTicketId = (int)$masterTicketId;
        if ($masterTicketId <= 0) {
            return [];
        }
        $sql = 'SELECT t.id, t.title, t.ticket_external_code, t.description, p.company_id, c.company AS company_name,
                       p.id AS problem_id, ts.name AS status_name
                FROM problems p
                INNER JOIN companies c ON c.id = p.company_id
                INNER JOIN problem_ticket_links l ON l.problem_id = p.id AND l.company_id = p.company_id AND l.deleted_at IS NULL
                INNER JOIN tickets t ON t.id = l.ticket_id AND t.company_id = l.company_id AND t.deleted_at IS NULL
                LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                WHERE p.master_ticket_id = ? AND p.deleted_at IS NULL';
        $types = 'i';
        $params = [$masterTicketId];
        if (is_array($allowedCompanyIds)) {
            $allowedCompanyIds = array_values(array_filter(array_map('intval', $allowedCompanyIds)));
            if (empty($allowedCompanyIds)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($allowedCompanyIds), '?'));
            $sql .= ' AND p.company_id IN (' . $placeholders . ')';
            $types .= str_repeat('i', count($allowedCompanyIds));
            foreach ($allowedCompanyIds as $cid) {
                $params[] = $cid;
            }
        }
        $sql .= ' ORDER BY c.company ASC, t.id ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        $bind = [$types];
        foreach ($params as $i => $v) {
            $bind[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_master_ticket_refresh_summary')) {
    function itm_master_ticket_refresh_summary($conn, $masterTicketId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $incidents = itm_master_ticket_list_all_incidents($conn, (int)$masterTicketId, null);
        $companies = [];
        $ticketIds = [];
        foreach ($incidents as $inc) {
            $companies[(int)$inc['company_id']] = (string)($inc['company_name'] ?? '');
            $ticketIds[] = (int)($inc['id'] ?? 0);
        }
        $summary = [
            'company_count' => count($companies),
            'ticket_count' => count($ticketIds),
            'companies' => $companies,
            'ticket_ids' => $ticketIds,
        ];
        $json = itm_master_ticket_encode_json($summary);
        $stmt = mysqli_prepare($conn, 'UPDATE master_tickets SET summary_json = ? WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'si', $json, $masterTicketId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_master_ticket_sync_problems')) {
    function itm_master_ticket_sync_problems($conn, array $masterRow, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $masterTicketId = (int)($masterRow['id'] ?? 0);
        $title = (string)($masterRow['title'] ?? '');
        $description = (string)($masterRow['description'] ?? '');
        $rootCause = (string)($masterRow['root_cause'] ?? '');
        $actorEmployeeId = (int)$actorEmployeeId;
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET title = ?, description = ?, root_cause = ?, updated_by = ?
             WHERE master_ticket_id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'sssii', $title, $description, $rootCause, $actorEmployeeId, $masterTicketId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_master_ticket_sync_to_incidents')) {
    function itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $actorCompanyId, $logSync = true)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $masterRow = itm_master_ticket_fetch_row($conn, $masterTicketId);
        if (!$masterRow) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        $incidents = itm_master_ticket_list_all_incidents($conn, $masterTicketId, null);
        if (empty($incidents)) {
            return ['ok' => false, 'error' => 'No linked incidents to sync.'];
        }

        mysqli_begin_transaction($conn);
        $failed = false;
        if (!itm_master_ticket_sync_problems($conn, $masterRow, $actorEmployeeId)) {
            $failed = true;
        }

        $ticketTitle = (string)($masterRow['title'] ?? '');
        $syncedIds = [];
        if (!$failed) {
            $upd = mysqli_prepare(
                $conn,
                'UPDATE tickets SET title = ?, description = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
            );
            if (!$upd) {
                $failed = true;
            } else {
                foreach ($incidents as $incident) {
                    $ticketId = (int)($incident['id'] ?? 0);
                    $ticketCompanyId = (int)($incident['company_id'] ?? 0);
                    if ($ticketId <= 0 || $ticketCompanyId <= 0) {
                        continue;
                    }
                    $newDescription = itm_master_ticket_build_ticket_description($masterRow, $incident['description'] ?? '');
                    mysqli_stmt_bind_param($upd, 'ssiii', $ticketTitle, $newDescription, $actorEmployeeId, $ticketId, $ticketCompanyId);
                    if (!mysqli_stmt_execute($upd)) {
                        $failed = true;
                        break;
                    }
                    $syncedIds[] = $ticketId;
                    if (function_exists('itm_ticket_activity_log')) {
                        require_once ROOT_PATH . 'includes/itm_ticket_activity.php';
                        itm_ticket_activity_log(
                            $conn,
                            $ticketCompanyId,
                            $ticketId,
                            $actorEmployeeId,
                            'master_ticket_synced',
                            ['master_ticket_id' => $masterTicketId]
                        );
                    }
                }
                mysqli_stmt_close($upd);
            }
        }

        if (!$failed) {
            itm_master_ticket_refresh_summary($conn, $masterTicketId);
        }

        if ($failed) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Master ticket sync failed — no changes saved.'];
        }

        mysqli_commit($conn);

        if ($logSync) {
            itm_master_ticket_log_history(
                $conn,
                $masterTicketId,
                'synced_to_tickets',
                $actorEmployeeId,
                $actorCompanyId,
                'Synced master ticket to ' . count($syncedIds) . ' incident ticket(s).',
                null,
                itm_master_ticket_encode_json([
                    'ticket_ids' => $syncedIds,
                    'ticket_count' => count($syncedIds),
                    'company_count' => count(array_unique(array_column($incidents, 'company_id'))),
                ])
            );
        }

        return ['ok' => true, 'ticket_count' => count($syncedIds), 'ticket_ids' => $syncedIds];
    }
}

if (!function_exists('itm_master_ticket_broadcast_to_incidents')) {
    function itm_master_ticket_broadcast_to_incidents($conn, $masterTicketId, $message, $actorEmployeeId, $actorCompanyId, $isInternal = 0)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $message = trim((string)$message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Message is required.'];
        }
        if (!itm_master_ticket_fetch_row($conn, $masterTicketId)) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        $incidents = itm_master_ticket_list_all_incidents($conn, $masterTicketId, null);
        if (empty($incidents)) {
            return ['ok' => false, 'error' => 'No linked incidents to update.'];
        }
        if (!function_exists('itm_ticket_comment_create')) {
            require_once ROOT_PATH . 'includes/itm_ticket_comments.php';
        }

        $body = itm_master_ticket_block_marker($masterTicketId) . "\n" . $message;
        $actorEmployeeId = (int)$actorEmployeeId;
        $actorCompanyId = (int)$actorCompanyId;
        $isInternal = (int)((bool)$isInternal);
        $postedIds = [];
        $errors = [];

        mysqli_begin_transaction($conn);
        foreach ($incidents as $incident) {
            $ticketId = (int)($incident['id'] ?? 0);
            $ticketCompanyId = (int)($incident['company_id'] ?? 0);
            if ($ticketId <= 0 || $ticketCompanyId <= 0) {
                continue;
            }
            $commentId = itm_ticket_comment_create($conn, $ticketCompanyId, $ticketId, $actorEmployeeId, $body, $isInternal);
            if ($commentId) {
                $postedIds[] = ['ticket_id' => $ticketId, 'company_id' => $ticketCompanyId, 'comment_id' => (int)$commentId];
            } else {
                $errors[] = '#' . $ticketId . ' (co ' . $ticketCompanyId . ')';
            }
        }

        if (empty($postedIds)) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not post message to any incident ticket.'];
        }

        $historySummary = 'Broadcast to ' . count($postedIds) . ' incident ticket(s): '
            . (function_exists('itm_ticket_comment_body_preview')
                ? itm_ticket_comment_body_preview($message, 420)
                : substr($message, 0, 420));
        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'broadcast_to_tickets',
            $actorEmployeeId,
            $actorCompanyId,
            $historySummary,
            null,
            itm_master_ticket_encode_json([
                'ticket_count' => count($postedIds),
                'ticket_ids' => array_column($postedIds, 'ticket_id'),
                'comments' => $postedIds,
                'message' => $message,
                'message_preview' => function_exists('itm_ticket_comment_body_preview')
                    ? itm_ticket_comment_body_preview($message, 200)
                    : substr($message, 0, 200),
                'is_internal' => $isInternal,
            ])
        );
        mysqli_commit($conn);

        return [
            'ok' => true,
            'ticket_count' => count($postedIds),
            'ticket_ids' => array_column($postedIds, 'ticket_id'),
            'errors' => $errors,
        ];
    }
}

if (!function_exists('itm_master_ticket_diff_fields')) {
    function itm_master_ticket_diff_fields(array $before, array $after, array $fields)
    {
        $changes = [];
        foreach ($fields as $field) {
            $oldVal = isset($before[$field]) ? (string)$before[$field] : '';
            $newVal = isset($after[$field]) ? (string)$after[$field] : '';
            if ($oldVal !== $newVal) {
                $changes[$field] = ['before' => $oldVal, 'after' => $newVal];
            }
        }
        return $changes;
    }
}

if (!function_exists('itm_master_ticket_can_manage')) {
    function itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId, $isAdmin = null)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        if ($isAdmin === null && function_exists('itm_is_admin')) {
            $isAdmin = itm_is_admin($conn, $employeeId);
        }
        if (!empty($isAdmin)) {
            return true;
        }
        $masterTicketId = (int)$masterTicketId;
        $sessionCompanyId = (int)$sessionCompanyId;
        if ($masterTicketId <= 0 || $sessionCompanyId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM problems WHERE master_ticket_id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $masterTicketId, $sessionCompanyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = $res && mysqli_num_rows($res) === 1;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_problem_create_master_ticket')) {
    function itm_problem_create_master_ticket($conn, $companyId, $problemId, $actorEmployeeId, $actorCompanyId = 0)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $actorCompanyId = (int)($actorCompanyId > 0 ? $actorCompanyId : $companyId);
        $problem = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$problem) {
            return ['ok' => false, 'error' => 'Problem not found.'];
        }
        if ((int)($problem['master_ticket_id'] ?? 0) > 0) {
            return ['ok' => false, 'error' => 'This problem already has a master ticket.'];
        }
        if (itm_problem_incident_count($conn, $companyId, $problemId) < 1) {
            return ['ok' => false, 'error' => 'Link at least one incident before creating a master ticket.'];
        }

        $title = (string)($problem['title'] ?? '');
        $description = (string)($problem['description'] ?? '');
        $rootCause = (string)($problem['root_cause'] ?? '');

        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO master_tickets (title, description, root_cause, active, created_by, updated_by) VALUES (?, ?, ?, 1, ?, ?)'
        );
        if (!$stmt) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not create master ticket.'];
        }
        mysqli_stmt_bind_param($stmt, 'sssii', $title, $description, $rootCause, $actorEmployeeId, $actorEmployeeId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not create master ticket.'];
        }
        $masterId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if ($masterId <= 0) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not create master ticket.'];
        }

        $upd = mysqli_prepare(
            $conn,
            'UPDATE problems SET master_ticket_id = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$upd) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not link problem to master ticket.'];
        }
        mysqli_stmt_bind_param($upd, 'iiii', $masterId, $actorEmployeeId, $problemId, $companyId);
        if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) !== 1) {
            mysqli_stmt_close($upd);
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not link problem to master ticket.'];
        }
        mysqli_stmt_close($upd);
        mysqli_commit($conn);

        itm_master_ticket_log_history(
            $conn,
            $masterId,
            'created',
            $actorEmployeeId,
            $actorCompanyId,
            'Created master ticket from problem #' . $problemId . '.',
            null,
            itm_master_ticket_encode_json(['problem_id' => $problemId, 'company_id' => $companyId])
        );

        $sync = itm_master_ticket_sync_to_incidents($conn, $masterId, $actorEmployeeId, $actorCompanyId, true);
        if (empty($sync['ok'])) {
            return ['ok' => true, 'master_ticket_id' => $masterId, 'sync_warning' => (string)($sync['error'] ?? '')];
        }
        return ['ok' => true, 'master_ticket_id' => $masterId, 'ticket_count' => (int)($sync['ticket_count'] ?? 0)];
    }
}

if (!function_exists('itm_master_ticket_update')) {
    function itm_master_ticket_update($conn, $masterTicketId, array $fields, $actorEmployeeId, $actorCompanyId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $before = itm_master_ticket_fetch_row($conn, $masterTicketId);
        if (!$before) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        $title = array_key_exists('title', $fields) ? trim((string)$fields['title']) : (string)($before['title'] ?? '');
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }
        $description = array_key_exists('description', $fields) ? (string)$fields['description'] : (string)($before['description'] ?? '');
        $rootCause = array_key_exists('root_cause', $fields) ? trim((string)$fields['root_cause']) : (string)($before['root_cause'] ?? '');
        $actorEmployeeId = (int)$actorEmployeeId;
        $actorCompanyId = (int)$actorCompanyId;

        $after = array_merge($before, [
            'title' => $title,
            'description' => $description,
            'root_cause' => $rootCause,
        ]);
        $changes = itm_master_ticket_diff_fields($before, $after, ['title', 'description', 'root_cause']);

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE master_tickets SET title = ?, description = ?, root_cause = ?, updated_by = ? WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Update failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'sssii', $title, $description, $rootCause, $actorEmployeeId, $masterTicketId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 0) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => 'Update failed.'];
        }
        mysqli_stmt_close($stmt);

        if (!empty($changes)) {
            itm_master_ticket_log_history(
                $conn,
                $masterTicketId,
                'fields_updated',
                $actorEmployeeId,
                $actorCompanyId,
                'Updated master ticket fields.',
                itm_master_ticket_encode_json($changes),
                null
            );
        }

        $sync = itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $actorCompanyId, true);
        if (empty($sync['ok'])) {
            return ['ok' => false, 'error' => (string)($sync['error'] ?? 'Sync failed after update.')];
        }
        return ['ok' => true, 'ticket_count' => (int)($sync['ticket_count'] ?? 0), 'changes' => $changes];
    }
}

if (!function_exists('itm_master_ticket_attach_problem')) {
    function itm_master_ticket_attach_problem($conn, $masterTicketId, $targetCompanyId, $targetProblemId, $actorEmployeeId, $sessionCompanyId, $isAdmin = null)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $targetCompanyId = (int)$targetCompanyId;
        $targetProblemId = (int)$targetProblemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $sessionCompanyId = (int)$sessionCompanyId;

        if ($isAdmin === null && function_exists('itm_is_admin')) {
            $isAdmin = itm_is_admin($conn, $actorEmployeeId);
        }
        if (!function_exists('itm_employee_has_company_access')) {
            require_once ROOT_PATH . 'includes/itm_company_session.php';
        }
        if (empty($isAdmin)) {
            if (!itm_employee_has_company_access($conn, $actorEmployeeId, $sessionCompanyId, $isAdmin)
                || !itm_employee_has_company_access($conn, $actorEmployeeId, $targetCompanyId, $isAdmin)) {
                return ['ok' => false, 'error' => 'You do not have access to attach problems from that company.'];
            }
        }

        if (!itm_master_ticket_fetch_row($conn, $masterTicketId)) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        $targetProblem = itm_problem_fetch_row($conn, $targetCompanyId, $targetProblemId);
        if (!$targetProblem) {
            return ['ok' => false, 'error' => 'Target problem not found.'];
        }
        if ((int)($targetProblem['master_ticket_id'] ?? 0) > 0) {
            return ['ok' => false, 'error' => 'Target problem is already on a master ticket.'];
        }
        if (itm_problem_incident_count($conn, $targetCompanyId, $targetProblemId) < 1) {
            return ['ok' => false, 'error' => 'Target problem has no linked incidents.'];
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET master_ticket_id = ?, updated_by = ? WHERE id = ? AND company_id = ? AND master_ticket_id IS NULL AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Attach failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $masterTicketId, $actorEmployeeId, $targetProblemId, $targetCompanyId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => 'Attach failed.'];
        }
        mysqli_stmt_close($stmt);

        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'problem_attached',
            $actorEmployeeId,
            $sessionCompanyId,
            'Attached problem #' . $targetProblemId . ' (company ' . $targetCompanyId . ').',
            null,
            itm_master_ticket_encode_json([
                'problem_id' => $targetProblemId,
                'company_id' => $targetCompanyId,
            ])
        );

        $sync = itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $sessionCompanyId, true);
        if (empty($sync['ok'])) {
            return ['ok' => true, 'sync_warning' => (string)($sync['error'] ?? '')];
        }
        return ['ok' => true, 'ticket_count' => (int)($sync['ticket_count'] ?? 0)];
    }
}

if (!function_exists('itm_master_ticket_after_incident_linked')) {
    function itm_master_ticket_after_incident_linked($conn, $companyId, $problemId, $ticketId, $actorEmployeeId, $actorCompanyId)
    {
        $problem = itm_problem_fetch_row($conn, (int)$companyId, (int)$problemId);
        $masterTicketId = (int)($problem['master_ticket_id'] ?? 0);
        if ($masterTicketId <= 0) {
            return ['ok' => true, 'skipped' => true];
        }
        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'incident_linked',
            $actorEmployeeId,
            $actorCompanyId,
            'Linked incident ticket #' . (int)$ticketId . ' to master.',
            null,
            itm_master_ticket_encode_json([
                'ticket_id' => (int)$ticketId,
                'company_id' => (int)$companyId,
                'problem_id' => (int)$problemId,
            ])
        );
        return itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $actorCompanyId, false);
    }
}

if (!function_exists('itm_master_ticket_bind_in_clause')) {
    /**
     * @param array<int, int> $ids
     * @return array{placeholders: string, types: string, params: array<int, int>}
     */
    function itm_master_ticket_bind_in_clause(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return ['placeholders' => '0', 'types' => '', 'params' => []];
        }

        return [
            'placeholders' => implode(',', array_fill(0, count($ids), '?')),
            'types' => str_repeat('i', count($ids)),
            'params' => $ids,
        ];
    }
}

if (!function_exists('itm_master_ticket_user_can_view')) {
    function itm_master_ticket_user_can_view($conn, $masterTicketId, array $allowedCompanyIds)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $masterTicketId = (int)$masterTicketId;
        if ($masterTicketId <= 0) {
            return false;
        }
        $in = itm_master_ticket_bind_in_clause($allowedCompanyIds);
        if ($in['placeholders'] === '0') {
            return false;
        }
        $sql = 'SELECT 1 FROM problems p
                WHERE p.master_ticket_id = ? AND p.deleted_at IS NULL
                  AND p.company_id IN (' . $in['placeholders'] . ')
                LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $types = 'i' . $in['types'];
        $params = array_merge([$masterTicketId], $in['params']);
        $bind = [$types];
        foreach ($params as $i => $v) {
            $bind[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_master_ticket_list_eligible_problems')) {
    /**
     * Existing major problems: ≥1 linked incident, no master yet, tenant in allowed companies.
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_master_ticket_list_eligible_problems($conn, array $allowedCompanyIds)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $in = itm_master_ticket_bind_in_clause($allowedCompanyIds);
        if ($in['placeholders'] === '0') {
            return [];
        }
        $sql = 'SELECT p.id, p.company_id, p.title, p.status, p.root_cause,
                       c.company AS company_name,
                       COUNT(DISTINCT l.ticket_id) AS incident_count
                FROM problems p
                INNER JOIN companies c ON c.id = p.company_id
                INNER JOIN problem_ticket_links l
                    ON l.problem_id = p.id AND l.company_id = p.company_id AND l.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND (p.master_ticket_id IS NULL OR p.master_ticket_id = 0)
                  AND p.company_id IN (' . $in['placeholders'] . ')
                GROUP BY p.id, p.company_id, p.title, p.status, p.root_cause, c.company
                HAVING incident_count >= 1
                ORDER BY c.company ASC, p.title ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        $bind = [$in['types']];
        $params = $in['params'];
        foreach ($params as $i => $v) {
            $bind[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_master_ticket_list_page')) {
    /**
     * Global master list visible when linked problems fall in allowed companies.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    function itm_master_ticket_list_page($conn, array $allowedCompanyIds, $search, $sort, $dir, $page, $perPage)
    {
        $result = ['rows' => [], 'total' => 0];
        if (!$conn instanceof mysqli) {
            return $result;
        }
        $in = itm_master_ticket_bind_in_clause($allowedCompanyIds);
        if ($in['placeholders'] === '0') {
            return $result;
        }

        $search = trim((string)$search);
        $sortMap = [
            'id' => 'm.id',
            'title' => 'm.title',
            'created_at' => 'm.created_at',
            'company_count' => 'company_count',
            'incident_count' => 'incident_count',
        ];
        $sortCol = $sortMap[$sort] ?? 'm.id';
        $dir = strtoupper((string)$dir) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $baseFrom = 'FROM master_tickets m
            INNER JOIN problems p ON p.master_ticket_id = m.id AND p.deleted_at IS NULL
            LEFT JOIN problem_ticket_links l
                ON l.problem_id = p.id AND l.company_id = p.company_id AND l.deleted_at IS NULL
            WHERE m.deleted_at IS NULL
              AND p.company_id IN (' . $in['placeholders'] . ')';
        $searchSql = '';
        $searchTypes = '';
        $searchParams = [];
        if ($search !== '') {
            $searchSql = ' AND (m.title LIKE ? OR m.description LIKE ? OR m.root_cause LIKE ?)';
            $like = '%' . $search . '%';
            $searchTypes = 'sss';
            $searchParams = [$like, $like, $like];
        }

        $countSql = 'SELECT COUNT(DISTINCT m.id) AS c ' . $baseFrom . $searchSql;
        $countStmt = mysqli_prepare($conn, $countSql);
        if (!$countStmt) {
            return $result;
        }
        $countTypes = $in['types'] . $searchTypes;
        $countParams = array_merge($in['params'], $searchParams);
        $countBind = [$countTypes];
        foreach ($countParams as $i => $v) {
            $countBind[] = &$countParams[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$countStmt], $countBind));
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        mysqli_stmt_close($countStmt);
        $result['total'] = (int)($countRow['c'] ?? 0);

        $listSql = 'SELECT m.id, m.title, m.description, m.root_cause, m.active, m.created_at, m.updated_at,
                           COUNT(DISTINCT p.company_id) AS company_count,
                           COUNT(DISTINCT l.ticket_id) AS incident_count
                    ' . $baseFrom . $searchSql . '
                    GROUP BY m.id, m.title, m.description, m.root_cause, m.active, m.created_at, m.updated_at
                    ORDER BY ' . $sortCol . ' ' . $dir . '
                    LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $listStmt = mysqli_prepare($conn, $listSql);
        if (!$listStmt) {
            return $result;
        }
        $listBind = [$countTypes];
        $listParams = $countParams;
        foreach ($listParams as $i => $v) {
            $listBind[] = &$listParams[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$listStmt], $listBind));
        mysqli_stmt_execute($listStmt);
        $listRes = mysqli_stmt_get_result($listStmt);
        while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
            $result['rows'][] = $row;
        }
        mysqli_stmt_close($listStmt);

        return $result;
    }
}

if (!function_exists('itm_master_ticket_count_live_rows')) {
    function itm_master_ticket_count_live_rows($conn): int
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM master_tickets WHERE deleted_at IS NULL');
        $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_master_ticket_seed_resolve_sample_ticket_id')) {
    /**
     * Why: Master ticket sample flow needs one linkable incident per tenant; reuse tickets sample seed when empty.
     */
    function itm_master_ticket_seed_resolve_sample_ticket_id($conn, int $companyId): int
    {
        if (!$conn instanceof mysqli || $companyId <= 0) {
            return 0;
        }
        if (!function_exists('itm_seed_insert_tickets_sample_row')) {
            require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
        }
        $seedError = '';
        itm_seed_insert_tickets_sample_row($conn, $companyId, $seedError);

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM tickets WHERE company_id = ? AND deleted_at IS NULL AND merged_into_ticket_id IS NULL AND is_archived = 0 ORDER BY id ASC LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
        mysqli_stmt_close($stmt);

        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_master_ticket_seed_five_company_sample')) {
    /**
     * Create one master ticket per seed company (1–5) with a linked problem + incident.
     *
     * @return int Number of master tickets created
     */
    function itm_master_ticket_seed_five_company_sample($conn, int $actorEmployeeId, int $actorCompanyId, &$error = ''): int
    {
        $error = '';
        if (!$conn instanceof mysqli) {
            $error = 'Database unavailable.';
            return 0;
        }
        $actorEmployeeId = (int)$actorEmployeeId;
        $actorCompanyId = (int)$actorCompanyId;
        if ($actorEmployeeId <= 0) {
            $error = 'Sample data requires a signed-in employee.';
            return 0;
        }
        if (itm_master_ticket_count_live_rows($conn) > 0) {
            $error = 'Sample data can only be added when no master tickets exist.';
            return 0;
        }

        if (!function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
        }
        if (!function_exists('itm_problem_create')) {
            require_once ROOT_PATH . 'includes/itm_problem_management.php';
        }
        if (!function_exists('itm_employee_has_company_access')) {
            require_once ROOT_PATH . 'includes/itm_company_session.php';
        }

        $isAdmin = function_exists('itm_is_admin') ? itm_is_admin($conn, $actorEmployeeId) : false;
        $created = 0;

        for ($companyId = 1; $companyId <= 5; $companyId++) {
            if (!$isAdmin && !itm_employee_has_company_access($conn, $actorEmployeeId, $companyId, $isAdmin)) {
                continue;
            }

            $companyStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? AND active = 1 LIMIT 1');
            $companyName = 'Company ' . $companyId;
            if ($companyStmt) {
                mysqli_stmt_bind_param($companyStmt, 'i', $companyId);
                mysqli_stmt_execute($companyStmt);
                $companyRes = mysqli_stmt_get_result($companyStmt);
                $companyRow = ($companyRes && ($fetched = mysqli_fetch_assoc($companyRes))) ? $fetched : null;
                mysqli_stmt_close($companyStmt);
                if (!is_array($companyRow)) {
                    continue;
                }
                $companyName = trim((string)($companyRow['company'] ?? $companyName));
            }

            $ticketId = itm_master_ticket_seed_resolve_sample_ticket_id($conn, $companyId);
            if ($ticketId <= 0) {
                $error = 'Could not resolve a sample incident ticket for company ' . $companyId . '.';
                return $created;
            }

            $tenantActorId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
            if ($tenantActorId <= 0) {
                $tenantActorId = $actorEmployeeId;
            }

            if (function_exists('itm_seed_sync_mysql_audit_session_for_company')) {
                itm_seed_sync_mysql_audit_session_for_company($conn, $companyId, $tenantActorId);
            }

            $problemTitle = 'Major incident — ' . $companyName;
            $problemResult = itm_problem_create($conn, $companyId, [
                'title' => $problemTitle,
                'description' => 'Cross-company major incident rollup demo for ' . $companyName . '.',
                'root_cause' => 'Pending root-cause analysis.',
                'status' => 'investigating',
                'owner_employee_id' => $tenantActorId,
            ], $tenantActorId);
            if (empty($problemResult['ok']) || (int)($problemResult['id'] ?? 0) <= 0) {
                $error = 'Could not create sample problem for company ' . $companyId . '.';
                return $created;
            }
            $problemId = (int)$problemResult['id'];

            $linkResult = itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $tenantActorId);
            if (empty($linkResult['ok'])) {
                $error = (string)($linkResult['error'] ?? 'Could not link sample incident.');
                return $created;
            }

            $masterResult = itm_problem_create_master_ticket($conn, $companyId, $problemId, $actorEmployeeId, $actorCompanyId);
            if (empty($masterResult['ok']) || (int)($masterResult['master_ticket_id'] ?? 0) <= 0) {
                $error = (string)($masterResult['error'] ?? 'Could not create sample master ticket.');
                return $created;
            }
            $created++;
        }

        if ($created === 0) {
            $error = 'No master tickets were created — check company access for companies 1–5.';
        }

        return $created;
    }
}

if (!function_exists('itm_ticket_resolve_master_ticket_id')) {
    /**
     * Resolve master ticket id for an incident via linked problems (read-only derived field).
     */
    function itm_ticket_resolve_master_ticket_id($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT p.master_ticket_id
             FROM problem_ticket_links l
             INNER JOIN problems p ON p.id = l.problem_id AND p.company_id = l.company_id
             WHERE l.ticket_id = ? AND l.company_id = ?
               AND l.deleted_at IS NULL AND p.deleted_at IS NULL
               AND p.master_ticket_id IS NOT NULL AND p.master_ticket_id > 0
             ORDER BY p.master_ticket_id ASC
             LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return (int)($row['master_ticket_id'] ?? 0);
    }
}

if (!function_exists('itm_master_ticket_list_linkable_tickets')) {
    /**
     * Tickets in a company not yet linked to the given problem (for master incident attach UI).
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_master_ticket_list_linkable_tickets($conn, $companyId, $problemId, $limit = 200)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $limit = max(1, min(500, (int)$limit));
        if ($companyId <= 0) {
            return [];
        }

        $sql = 'SELECT t.id, t.ticket_external_code, t.title
                FROM tickets t
                WHERE t.company_id = ? AND t.deleted_at IS NULL AND t.merged_into_ticket_id IS NULL';
        if ($problemId > 0) {
            $sql .= ' AND NOT EXISTS (
                SELECT 1 FROM problem_ticket_links l
                WHERE l.ticket_id = t.id AND l.company_id = t.company_id
                  AND l.problem_id = ? AND l.deleted_at IS NULL
            )';
        }
        $sql .= ' ORDER BY t.id DESC LIMIT ' . $limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        if ($problemId > 0) {
            mysqli_stmt_bind_param($stmt, 'ii', $companyId, $problemId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_master_ticket_linked_problems_by_company')) {
    /**
     * Problems on a master ticket grouped by company_id.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    function itm_master_ticket_linked_problems_by_company($conn, $masterTicketId, array $allowedCompanyIds = null)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $masterTicketId = (int)$masterTicketId;
        if ($masterTicketId <= 0) {
            return [];
        }
        $sql = 'SELECT p.id, p.company_id, p.title, c.company AS company_name
                FROM problems p
                INNER JOIN companies c ON c.id = p.company_id
                WHERE p.master_ticket_id = ? AND p.deleted_at IS NULL';
        $types = 'i';
        $params = [$masterTicketId];
        if (is_array($allowedCompanyIds)) {
            $allowedCompanyIds = array_values(array_filter(array_map('intval', $allowedCompanyIds)));
            if ($allowedCompanyIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($allowedCompanyIds), '?'));
            $sql .= ' AND p.company_id IN (' . $placeholders . ')';
            $types .= str_repeat('i', count($allowedCompanyIds));
            foreach ($allowedCompanyIds as $cid) {
                $params[] = $cid;
            }
        }
        $sql .= ' ORDER BY c.company ASC, p.title ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        $bind = [$types];
        foreach ($params as $i => $v) {
            $bind[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $grouped = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $cid = (int)($row['company_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [];
            }
            $grouped[$cid][] = $row;
        }
        mysqli_stmt_close($stmt);

        return $grouped;
    }
}

if (!function_exists('itm_master_ticket_list_linkable_tickets_for_master')) {
    /**
     * Tickets in allowed companies not yet linked as incidents on this master ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_master_ticket_list_linkable_tickets_for_master($conn, $masterTicketId, array $allowedCompanyIds, $limit = 500)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $masterTicketId = (int)$masterTicketId;
        $limit = max(1, min(1000, (int)$limit));
        $in = itm_master_ticket_bind_in_clause($allowedCompanyIds);
        if ($masterTicketId <= 0 || $in['placeholders'] === '0') {
            return [];
        }

        $sql = 'SELECT t.id, t.company_id, t.ticket_external_code, t.title, c.company AS company_name
                FROM tickets t
                INNER JOIN companies c ON c.id = t.company_id
                WHERE t.company_id IN (' . $in['placeholders'] . ')
                  AND t.deleted_at IS NULL AND t.merged_into_ticket_id IS NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM problem_ticket_links l2
                    INNER JOIN problems p2 ON p2.id = l2.problem_id AND p2.company_id = l2.company_id
                    WHERE l2.ticket_id = t.id AND l2.company_id = t.company_id
                      AND l2.deleted_at IS NULL AND p2.deleted_at IS NULL
                      AND p2.master_ticket_id = ?
                  )
                ORDER BY c.company ASC, t.id DESC
                LIMIT ' . $limit;

        $types = $in['types'] . 'i';
        $params = array_merge($in['params'], [$masterTicketId]);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        $bind = [$types];
        foreach ($params as $i => $v) {
            $bind[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_master_ticket_resolve_link_problem_for_company')) {
    /**
     * Pick the problem on this master for linking a ticket in the given company.
     */
    function itm_master_ticket_resolve_link_problem_for_company(
        $conn,
        $masterTicketId,
        $companyId,
        array $problemsByCompany,
        $overrideProblemId = 0
    ) {
        $companyId = (int)$companyId;
        $overrideProblemId = (int)$overrideProblemId;
        if ($overrideProblemId > 0) {
            $problem = itm_problem_fetch_row($conn, $companyId, $overrideProblemId);
            if ($problem && (int)($problem['master_ticket_id'] ?? 0) === (int)$masterTicketId) {
                return ['ok' => true, 'problem_id' => $overrideProblemId];
            }
        }

        $candidates = $problemsByCompany[$companyId] ?? [];
        if (count($candidates) === 1) {
            return ['ok' => true, 'problem_id' => (int)($candidates[0]['id'] ?? 0)];
        }
        if (count($candidates) === 0) {
            return ['ok' => false, 'error' => 'No problem on this master for company #' . $companyId . ' — attach a problem first.'];
        }

        return [
            'ok' => false,
            'error' => 'Company #' . $companyId . ' has multiple problems on this master — use the optional problem filter.',
        ];
    }
}

if (!function_exists('itm_master_ticket_ensure_problem_for_company')) {
    /**
     * Resolve or auto-create a problem on this master for the given company (incident link flow).
     *
     * @param array<int, array<int, array<string, mixed>>> $problemsByCompany updated in place when a problem is created
     */
    function itm_master_ticket_ensure_problem_for_company(
        $conn,
        $masterTicketId,
        $companyId,
        array &$problemsByCompany,
        $overrideProblemId,
        $actorEmployeeId,
        $sessionCompanyId
    ) {
        $resolved = itm_master_ticket_resolve_link_problem_for_company(
            $conn,
            $masterTicketId,
            $companyId,
            $problemsByCompany,
            $overrideProblemId
        );
        if (!empty($resolved['ok'])) {
            return $resolved;
        }

        $candidates = $problemsByCompany[$companyId] ?? [];
        if (count($candidates) > 1) {
            return $resolved;
        }

        $masterTicketId = (int)$masterTicketId;
        $companyId = (int)$companyId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $sessionCompanyId = (int)$sessionCompanyId;
        $master = itm_master_ticket_fetch_row($conn, $masterTicketId);
        if (!$master) {
            return ['ok' => false, 'error' => 'Master ticket not found.'];
        }
        if (!function_exists('itm_problem_create')) {
            require_once ROOT_PATH . 'includes/itm_problem_management.php';
        }

        $create = itm_problem_create($conn, $companyId, [
            'title' => (string)($master['title'] ?? ''),
            'description' => (string)($master['description'] ?? ''),
            'root_cause' => (string)($master['root_cause'] ?? ''),
            'status' => 'investigating',
        ], $actorEmployeeId);
        if (empty($create['ok']) || (int)($create['id'] ?? 0) <= 0) {
            return ['ok' => false, 'error' => (string)($create['error'] ?? 'Could not create problem for company #' . $companyId . '.')];
        }
        $problemId = (int)$create['id'];

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET master_ticket_id = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not link new problem to master ticket.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $masterTicketId, $actorEmployeeId, $problemId, $companyId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => 'Could not link new problem to master ticket.'];
        }
        mysqli_stmt_close($stmt);

        itm_master_ticket_log_history(
            $conn,
            $masterTicketId,
            'problem_attached',
            $actorEmployeeId,
            $sessionCompanyId,
            'Auto-created problem #' . $problemId . ' for company ' . $companyId . ' while linking incidents.',
            null,
            itm_master_ticket_encode_json(['problem_id' => $problemId, 'company_id' => $companyId, 'auto_created' => true])
        );

        $companyName = '';
        $nameStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? LIMIT 1');
        if ($nameStmt) {
            mysqli_stmt_bind_param($nameStmt, 'i', $companyId);
            mysqli_stmt_execute($nameStmt);
            $nameRes = mysqli_stmt_get_result($nameStmt);
            $nameRow = $nameRes ? mysqli_fetch_assoc($nameRes) : null;
            mysqli_stmt_close($nameStmt);
            $companyName = (string)($nameRow['company'] ?? '');
        }

        $problemsByCompany[$companyId] = [[
            'id' => $problemId,
            'company_id' => $companyId,
            'title' => (string)($master['title'] ?? ''),
            'company_name' => $companyName,
        ]];

        return ['ok' => true, 'problem_id' => $problemId, 'auto_created' => true];
    }
}

if (!function_exists('itm_master_ticket_attach_problems_bulk')) {
    /**
     * @param array<int, array{company_id: int, problem_id: int}> $targets
     */
    function itm_master_ticket_attach_problems_bulk($conn, $masterTicketId, array $targets, $actorEmployeeId, $sessionCompanyId)
    {
        $attached = 0;
        $errors = [];
        foreach ($targets as $target) {
            $companyId = (int)($target['company_id'] ?? 0);
            $problemId = (int)($target['problem_id'] ?? 0);
            if ($companyId <= 0 || $problemId <= 0) {
                continue;
            }
            $result = itm_master_ticket_attach_problem(
                $conn,
                $masterTicketId,
                $companyId,
                $problemId,
                $actorEmployeeId,
                $sessionCompanyId
            );
            if (!empty($result['ok'])) {
                $attached++;
            } else {
                $errors[] = 'Problem #' . $problemId . ' (company ' . $companyId . '): ' . (string)($result['error'] ?? 'failed');
            }
        }
        if ($attached === 0 && $errors === []) {
            return ['ok' => false, 'error' => 'No problems selected.', 'attached' => 0];
        }

        return [
            'ok' => $attached > 0,
            'attached' => $attached,
            'errors' => $errors,
            'error' => $errors !== [] ? implode(' ', $errors) : '',
        ];
    }
}

if (!function_exists('itm_master_ticket_link_incidents_bulk')) {
    /**
     * Link incident tickets to a problem that is already on this master ticket.
     *
     * @param array<int, int> $ticketIds
     */
    function itm_master_ticket_link_incidents_bulk(
        $conn,
        $masterTicketId,
        $companyId,
        $problemId,
        array $ticketIds,
        $actorEmployeeId,
        $sessionCompanyId
    ) {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        if ($masterTicketId <= 0 || $companyId <= 0 || $problemId <= 0) {
            return ['ok' => false, 'error' => 'Invalid link request.'];
        }
        $problem = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$problem || (int)($problem['master_ticket_id'] ?? 0) !== $masterTicketId) {
            return ['ok' => false, 'error' => 'Problem is not linked to this master ticket.'];
        }
        if (!function_exists('itm_problem_link_ticket')) {
            require_once ROOT_PATH . 'includes/itm_problem_management.php';
        }

        $linked = 0;
        $errors = [];
        foreach ($ticketIds as $ticketId) {
            $ticketId = (int)$ticketId;
            if ($ticketId <= 0) {
                continue;
            }
            $result = itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $actorEmployeeId);
            if (!empty($result['ok'])) {
                $linked++;
            } else {
                $errors[] = 'Ticket #' . $ticketId . ': ' . (string)($result['error'] ?? 'failed');
            }
        }
        if ($linked === 0 && $errors === []) {
            return ['ok' => false, 'error' => 'No tickets selected.', 'linked' => 0];
        }
        if ($linked > 0) {
            itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $sessionCompanyId, false);
        }

        return [
            'ok' => $linked > 0,
            'linked' => $linked,
            'errors' => $errors,
            'error' => $errors !== [] ? implode(' ', $errors) : '',
        ];
    }
}

if (!function_exists('itm_master_ticket_link_incidents_multi_company_bulk')) {
    /**
     * Link tickets from multiple companies; resolves problem per company on this master.
     *
     * @param array<int, array{company_id: int, ticket_id: int}> $ticketTargets
     */
    function itm_master_ticket_link_incidents_multi_company_bulk(
        $conn,
        $masterTicketId,
        array $ticketTargets,
        $overrideProblemId,
        array $allowedCompanyIds,
        $actorEmployeeId,
        $sessionCompanyId
    ) {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $masterTicketId = (int)$masterTicketId;
        $overrideProblemId = (int)$overrideProblemId;
        if ($masterTicketId <= 0) {
            return ['ok' => false, 'error' => 'Invalid link request.'];
        }
        if (!function_exists('itm_problem_link_ticket')) {
            require_once ROOT_PATH . 'includes/itm_problem_management.php';
        }

        $problemsByCompany = itm_master_ticket_linked_problems_by_company($conn, $masterTicketId, $allowedCompanyIds);
        $overrideCompanyId = 0;
        if ($overrideProblemId > 0) {
            foreach ($problemsByCompany as $cid => $problems) {
                foreach ($problems as $problemRow) {
                    if ((int)($problemRow['id'] ?? 0) === $overrideProblemId) {
                        $overrideCompanyId = (int)$cid;
                        break 2;
                    }
                }
            }
        }

        $linked = 0;
        $errors = [];
        foreach ($ticketTargets as $target) {
            $companyId = (int)($target['company_id'] ?? 0);
            $ticketId = (int)($target['ticket_id'] ?? 0);
            if ($companyId <= 0 || $ticketId <= 0) {
                continue;
            }
            $problemOverride = ($overrideCompanyId > 0 && $companyId === $overrideCompanyId) ? $overrideProblemId : 0;
            $resolved = itm_master_ticket_ensure_problem_for_company(
                $conn,
                $masterTicketId,
                $companyId,
                $problemsByCompany,
                $problemOverride,
                $actorEmployeeId,
                $sessionCompanyId
            );
            if (empty($resolved['ok'])) {
                $errors[] = 'Ticket #' . $ticketId . ': ' . (string)($resolved['error'] ?? 'could not resolve problem');
                continue;
            }
            $problemId = (int)($resolved['problem_id'] ?? 0);
            $result = itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $actorEmployeeId);
            if (!empty($result['ok'])) {
                $linked++;
            } else {
                $errors[] = 'Ticket #' . $ticketId . ': ' . (string)($result['error'] ?? 'failed');
            }
        }
        if ($linked === 0 && $errors === []) {
            return ['ok' => false, 'error' => 'No tickets selected.', 'linked' => 0];
        }
        if ($linked > 0) {
            itm_master_ticket_sync_to_incidents($conn, $masterTicketId, $actorEmployeeId, $sessionCompanyId, false);
        }

        return [
            'ok' => $linked > 0,
            'linked' => $linked,
            'errors' => $errors,
            'error' => $errors !== [] ? implode(' ', $errors) : '',
        ];
    }
}
