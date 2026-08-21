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
            $isAdmin = itm_is_admin();
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
            $isAdmin = itm_is_admin();
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
            $isAdmin = itm_is_admin();
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
