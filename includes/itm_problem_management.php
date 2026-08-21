<?php
/**
 * Problem Management and Known Error Database — tenant-scoped CRUD, ticket linking, suggestions, KB publish.
 */

if (!function_exists('itm_problem_status_options')) {
    function itm_problem_status_options()
    {
        return [
            'investigating' => 'Investigating',
            'known_error' => 'Known Error',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }
}

if (!function_exists('itm_problem_status_badge')) {
    function itm_problem_status_badge($status)
    {
        $status = strtolower(trim((string)$status));
        $labels = itm_problem_status_options();
        $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
        $class = 'badge-secondary';
        if ($status === 'investigating') {
            $class = 'badge-warning';
        } elseif ($status === 'known_error') {
            $class = 'badge-info';
        } elseif ($status === 'resolved') {
            $class = 'badge-success';
        } elseif ($status === 'closed') {
            $class = 'badge-danger';
        }
        return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('itm_problem_allowed_transitions')) {
    function itm_problem_allowed_transitions()
    {
        return [
            'investigating' => ['investigating', 'known_error', 'resolved', 'closed'],
            'known_error' => ['known_error', 'resolved', 'closed', 'investigating'],
            'resolved' => ['resolved', 'closed', 'investigating'],
            'closed' => ['closed', 'investigating'],
        ];
    }
}

if (!function_exists('itm_problem_tokenize_search_text')) {
    function itm_problem_tokenize_search_text($text, $minLength = 4)
    {
        $text = strtolower(preg_replace('/[^a-z0-9\s]+/i', ' ', (string)$text));
        $parts = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) >= (int)$minLength) {
                $tokens[$part] = true;
            }
        }
        return array_keys($tokens);
    }
}

if (!function_exists('itm_problem_fetch_row')) {
    function itm_problem_fetch_row($conn, $companyId, $problemId)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        if ($companyId <= 0 || $problemId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT p.*, CONCAT(COALESCE(e.first_name, ""), " ", COALESCE(e.last_name, "")) AS owner_name, e.username AS owner_username
             FROM problems p
             LEFT JOIN employees e ON e.id = p.owner_employee_id AND e.company_id = p.company_id
             WHERE p.id = ? AND p.company_id = ? AND p.deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $problemId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_problem_dispatch_events')) {
    function itm_problem_dispatch_events($conn, $companyId, $eventType, array $problemRow, array $extra = [])
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $companyId = (int)$companyId;
        $context = array_merge([
            'problem_id' => (int)($problemRow['id'] ?? 0),
            'title' => (string)($problemRow['title'] ?? ''),
            'status' => (string)($problemRow['status'] ?? ''),
            'owner_employee_id' => (int)($problemRow['owner_employee_id'] ?? 0),
            'company_id' => $companyId,
            'automation_depth' => (int)($extra['automation_depth'] ?? 0),
        ], $extra);

        if (function_exists('itm_automation_rules_dispatch')) {
            require_once ROOT_PATH . 'includes/itm_automation_rules.php';
            itm_automation_rules_dispatch($conn, $companyId, $eventType, $context);
        }

        if (!function_exists('itm_webhook_queue_enqueue')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
        }
        $payload = array_merge([
            'event' => $eventType,
            'company_id' => $companyId,
            'problem_id' => (int)($problemRow['id'] ?? 0),
            'title' => (string)($problemRow['title'] ?? ''),
            'status' => (string)($problemRow['status'] ?? ''),
            'owner_employee_id' => (int)($problemRow['owner_employee_id'] ?? 0),
            'changed_at' => date('Y-m-d H:i:s'),
        ], $extra);
        itm_webhook_queue_enqueue($conn, $companyId, $eventType, $payload);
    }
}

if (!function_exists('itm_problem_create')) {
    function itm_problem_create($conn, $companyId, array $data, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $companyId = (int)$companyId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $title = trim((string)($data['title'] ?? ''));
        if ($companyId <= 0 || $title === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }
        $description = (string)($data['description'] ?? '');
        $rootCause = trim((string)($data['root_cause'] ?? ''));
        $status = strtolower(trim((string)($data['status'] ?? 'investigating')));
        $allowed = itm_problem_status_options();
        if (!isset($allowed[$status])) {
            $status = 'investigating';
        }
        $ownerId = (int)($data['owner_employee_id'] ?? 0);
        if ($ownerId <= 0) {
            $ownerId = $actorEmployeeId > 0 ? $actorEmployeeId : 0;
        }
        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO problems (company_id, title, description, root_cause, status, owner_employee_id, resolved_at, active, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), ?, 1, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Insert failed.'];
        }
        mysqli_stmt_bind_param(
            $stmt,
            'issssissi',
            $companyId,
            $title,
            $description,
            $rootCause,
            $status,
            $ownerId,
            $resolvedAt,
            $actorEmployeeId,
            $actorEmployeeId
        );
        $ok = mysqli_stmt_execute($stmt);
        $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if ($newId <= 0) {
            return ['ok' => false, 'error' => 'Could not create problem.'];
        }
        $row = itm_problem_fetch_row($conn, $companyId, $newId);
        if ($row) {
            itm_problem_dispatch_events($conn, $companyId, 'problem.created', $row);
        }
        return ['ok' => true, 'id' => $newId, 'row' => $row];
    }
}

if (!function_exists('itm_problem_update')) {
    function itm_problem_update($conn, $companyId, $problemId, array $data, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $existing = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Problem not found.'];
        }

        $title = trim((string)($data['title'] ?? $existing['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }
        $description = array_key_exists('description', $data) ? (string)$data['description'] : (string)($existing['description'] ?? '');
        $rootCause = array_key_exists('root_cause', $data) ? trim((string)$data['root_cause']) : trim((string)($existing['root_cause'] ?? ''));
        $newStatus = strtolower(trim((string)($data['status'] ?? $existing['status'] ?? 'investigating')));
        $oldStatus = strtolower(trim((string)($existing['status'] ?? 'investigating')));
        $transitions = itm_problem_allowed_transitions();
        if (!isset($transitions[$oldStatus]) || !in_array($newStatus, $transitions[$oldStatus], true)) {
            $newStatus = $oldStatus;
        }
        $ownerId = array_key_exists('owner_employee_id', $data) ? (int)$data['owner_employee_id'] : (int)($existing['owner_employee_id'] ?? 0);
        $ownerBind = $ownerId > 0 ? $ownerId : null;
        $resolvedAt = $existing['resolved_at'] ?? null;
        if (in_array($newStatus, ['resolved', 'closed'], true) && empty($resolvedAt)) {
            $resolvedAt = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'investigating' || $newStatus === 'known_error') {
            $resolvedAt = null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET title = ?, description = ?, root_cause = ?, status = ?, owner_employee_id = ?, resolved_at = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Update failed.'];
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssisiii',
            $title,
            $description,
            $rootCause,
            $newStatus,
            $ownerBind,
            $resolvedAt,
            $actorEmployeeId,
            $problemId,
            $companyId
        );
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update problem.'];
        }
        $row = itm_problem_fetch_row($conn, $companyId, $problemId);
        if ($row && $newStatus !== $oldStatus) {
            itm_problem_dispatch_events($conn, $companyId, 'problem.status_changed', $row, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
        }
        return ['ok' => true, 'row' => $row];
    }
}

if (!function_exists('itm_problem_soft_delete')) {
    function itm_problem_soft_delete($conn, $companyId, $problemId, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problems SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $actorEmployeeId, $actorEmployeeId, $problemId, $companyId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_problem_ticket_is_linkable')) {
    function itm_problem_ticket_is_linkable($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND merged_into_ticket_id IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = $res && mysqli_num_rows($res) === 1;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_problem_link_ticket')) {
    function itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = (int)$actorEmployeeId;
        if ($companyId <= 0 || $problemId <= 0 || $ticketId <= 0) {
            return ['ok' => false, 'error' => 'Invalid link request.'];
        }
        if (!itm_problem_fetch_row($conn, $companyId, $problemId)) {
            return ['ok' => false, 'error' => 'Problem not found.'];
        }
        if (!itm_problem_ticket_is_linkable($conn, $companyId, $ticketId)) {
            return ['ok' => false, 'error' => 'Ticket is not available for linking.'];
        }

        $reactivate = mysqli_prepare(
            $conn,
            'UPDATE problem_ticket_links SET active = 1, deleted_at = NULL, deleted_by = NULL, linked_at = NOW(), linked_by = ?, updated_by = ?
             WHERE company_id = ? AND problem_id = ? AND ticket_id = ? LIMIT 1'
        );
        if ($reactivate) {
            mysqli_stmt_bind_param($reactivate, 'iiiii', $actorEmployeeId, $actorEmployeeId, $companyId, $problemId, $ticketId);
            mysqli_stmt_execute($reactivate);
            $affected = mysqli_stmt_affected_rows($reactivate);
            mysqli_stmt_close($reactivate);
            if ($affected === 1) {
                if (function_exists('itm_ticket_activity_log')) {
                    require_once ROOT_PATH . 'includes/itm_ticket_activity.php';
                    itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'problem_linked', ['problem_id' => $problemId]);
                }
                return ['ok' => true, 'reactivated' => true];
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO problem_ticket_links (company_id, problem_id, ticket_id, linked_by, active, created_by, updated_by)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Link insert failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiiiii', $companyId, $problemId, $ticketId, $actorEmployeeId, $actorEmployeeId, $actorEmployeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not link ticket (may already be linked).'];
        }
        if (function_exists('itm_ticket_activity_log')) {
            require_once ROOT_PATH . 'includes/itm_ticket_activity.php';
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'problem_linked', ['problem_id' => $problemId]);
        }
        return ['ok' => true];
    }
}

if (!function_exists('itm_problem_unlink_ticket')) {
    function itm_problem_unlink_ticket($conn, $companyId, $problemId, $ticketId, $actorEmployeeId)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE problem_ticket_links SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?
             WHERE company_id = ? AND problem_id = ? AND ticket_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Unlink failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiiii', $actorEmployeeId, $actorEmployeeId, $companyId, $problemId, $ticketId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        if ($ok && function_exists('itm_ticket_activity_log')) {
            require_once ROOT_PATH . 'includes/itm_ticket_activity.php';
            itm_ticket_activity_log($conn, (int)$companyId, (int)$ticketId, (int)$actorEmployeeId, 'problem_unlinked', ['problem_id' => (int)$problemId]);
        }
        return ['ok' => $ok, 'error' => $ok ? '' : 'Link not found.'];
    }
}

if (!function_exists('itm_problem_list_for_ticket')) {
    function itm_problem_list_for_ticket($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT p.id, p.title, p.status, p.knowledge_base_id
             FROM problem_ticket_links l
             INNER JOIN problems p ON p.id = l.problem_id AND p.company_id = l.company_id
             WHERE l.company_id = ? AND l.ticket_id = ? AND l.deleted_at IS NULL AND p.deleted_at IS NULL
             ORDER BY p.id DESC'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
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

if (!function_exists('itm_problem_list_incidents')) {
    function itm_problem_list_incidents($conn, $companyId, $problemId)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT t.id, t.title, t.ticket_external_code, ts.name AS status_name
             FROM problem_ticket_links l
             INNER JOIN tickets t ON t.id = l.ticket_id AND t.company_id = l.company_id
             LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
             WHERE l.company_id = ? AND l.problem_id = ? AND l.deleted_at IS NULL AND t.deleted_at IS NULL
             ORDER BY t.id DESC'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $problemId);
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

if (!function_exists('itm_problem_incident_count')) {
    function itm_problem_incident_count($conn, $companyId, $problemId)
    {
        $rows = itm_problem_list_incidents($conn, (int)$companyId, (int)$problemId);
        return count($rows);
    }
}

if (!function_exists('itm_known_error_fetch_for_problem')) {
    function itm_known_error_fetch_for_problem($conn, $companyId, $problemId)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM known_errors WHERE company_id = ? AND problem_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id DESC LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $problemId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_known_error_publish_to_kb')) {
    function itm_known_error_publish_to_kb($conn, $companyId, $problemId, array $knownErrorRow, $actorEmployeeId, $existingKbId = 0)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.', 'knowledge_base_id' => 0];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $problem = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$problem) {
            return ['ok' => false, 'error' => 'Problem not found.', 'knowledge_base_id' => 0];
        }

        $title = trim((string)($knownErrorRow['title'] ?? $problem['title'] ?? ''));
        $workaround = trim((string)($knownErrorRow['workaround'] ?? ''));
        $rootCause = trim((string)($problem['root_cause'] ?? ''));
        if ($title === '' || $workaround === '') {
            return ['ok' => false, 'error' => 'Title and workaround required for KB publish.', 'knowledge_base_id' => 0];
        }

        $content = $workaround;
        if ($rootCause !== '') {
            $content .= "\n\nRoot cause:\n" . $rootCause;
        }
        $content .= "\n\nLinked problem record #" . $problemId . '.';

        $kbId = (int)$existingKbId;
        if ($kbId <= 0) {
            $kbId = (int)($problem['knowledge_base_id'] ?? 0);
        }

        if ($kbId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE knowledge_base SET category = ?, title = ?, content = ?, active = 1, updated_by = ? WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'KB update failed.', 'knowledge_base_id' => 0];
            }
            $category = 'Known Errors';
            mysqli_stmt_bind_param($stmt, 'sssiii', $category, $title, $content, $actorEmployeeId, $kbId, $companyId);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$ok) {
                return ['ok' => false, 'error' => 'KB update failed.', 'knowledge_base_id' => 0];
            }
        } else {
            $baseTitle = $title;
            $attempt = 0;
            while ($attempt < 5) {
                $tryTitle = $attempt === 0 ? $baseTitle : $baseTitle . ' (KE-' . $problemId . '-' . $attempt . ')';
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO knowledge_base (company_id, employee_id, category, title, content, active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
                );
                if (!$stmt) {
                    break;
                }
                $category = 'Known Errors';
                mysqli_stmt_bind_param($stmt, 'iisssii', $companyId, $actorEmployeeId, $category, $tryTitle, $content, $actorEmployeeId, $actorEmployeeId);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($ok) {
                    $kbId = (int)mysqli_insert_id($conn);
                    $title = $tryTitle;
                    break;
                }
                $attempt++;
            }
            if ($kbId <= 0) {
                return ['ok' => false, 'error' => 'Could not create KB article (title collision?).', 'knowledge_base_id' => 0];
            }
        }

        $pStmt = mysqli_prepare($conn, 'UPDATE problems SET knowledge_base_id = ?, updated_by = ? WHERE id = ? AND company_id = ? LIMIT 1');
        if ($pStmt) {
            mysqli_stmt_bind_param($pStmt, 'iiii', $kbId, $actorEmployeeId, $problemId, $companyId);
            mysqli_stmt_execute($pStmt);
            mysqli_stmt_close($pStmt);
        }

        return ['ok' => true, 'knowledge_base_id' => $kbId, 'title' => $title];
    }
}

if (!function_exists('itm_known_error_upsert')) {
    function itm_known_error_upsert($conn, $companyId, $problemId, array $data, $actorEmployeeId, $publishKb = false)
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable.'];
        }
        $companyId = (int)$companyId;
        $problemId = (int)$problemId;
        $actorEmployeeId = (int)$actorEmployeeId;
        $problem = itm_problem_fetch_row($conn, $companyId, $problemId);
        if (!$problem) {
            return ['ok' => false, 'error' => 'Problem not found.'];
        }

        $title = trim((string)($data['title'] ?? $problem['title'] ?? ''));
        $workaround = trim((string)($data['workaround'] ?? ''));
        $keywords = trim((string)($data['symptom_keywords'] ?? ''));
        if ($title === '' || $workaround === '') {
            return ['ok' => false, 'error' => 'Known error title and workaround are required.'];
        }

        $existing = itm_known_error_fetch_for_problem($conn, $companyId, $problemId);
        mysqli_begin_transaction($conn);
        $failed = false;

        if ($existing) {
            $keId = (int)$existing['id'];
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE known_errors SET title = ?, workaround = ?, symptom_keywords = ?, active = 1, deleted_at = NULL, deleted_by = NULL, updated_by = ?
                 WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssiii', $title, $workaround, $keywords, $actorEmployeeId, $keId, $companyId);
                if (!mysqli_stmt_execute($stmt)) {
                    $failed = true;
                }
                mysqli_stmt_close($stmt);
            } else {
                $failed = true;
            }
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO known_errors (company_id, problem_id, title, workaround, symptom_keywords, active, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iisssii', $companyId, $problemId, $title, $workaround, $keywords, $actorEmployeeId, $actorEmployeeId);
                if (!mysqli_stmt_execute($stmt)) {
                    $failed = true;
                }
                $keId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
            } else {
                $failed = true;
            }
        }

        $status = 'known_error';
        $pStmt = mysqli_prepare($conn, 'UPDATE problems SET status = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if ($pStmt) {
            mysqli_stmt_bind_param($pStmt, 'siii', $status, $actorEmployeeId, $problemId, $companyId);
            if (!mysqli_stmt_execute($pStmt)) {
                $failed = true;
            }
            mysqli_stmt_close($pStmt);
        } else {
            $failed = true;
        }

        if ($failed) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'Could not save known error.'];
        }

        $kbId = 0;
        if ($publishKb) {
            $kbResult = itm_known_error_publish_to_kb($conn, $companyId, $problemId, ['title' => $title, 'workaround' => $workaround], $actorEmployeeId, (int)($existing['knowledge_base_id'] ?? 0));
            if (empty($kbResult['ok'])) {
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => (string)($kbResult['error'] ?? 'KB publish failed.')];
            }
            $kbId = (int)($kbResult['knowledge_base_id'] ?? 0);
            if ($kbId > 0 && !empty($keId)) {
                $kStmt = mysqli_prepare($conn, 'UPDATE known_errors SET knowledge_base_id = ? WHERE id = ? AND company_id = ? LIMIT 1');
                if ($kStmt) {
                    mysqli_stmt_bind_param($kStmt, 'iii', $kbId, $keId, $companyId);
                    mysqli_stmt_execute($kStmt);
                    mysqli_stmt_close($kStmt);
                }
            }
        }

        mysqli_commit($conn);
        $row = itm_problem_fetch_row($conn, $companyId, $problemId);
        if ($row) {
            itm_problem_dispatch_events($conn, $companyId, 'known_error.published', $row, [
                'known_error_title' => $title,
                'knowledge_base_id' => $kbId,
            ]);
        }
        return ['ok' => true, 'known_error_id' => (int)($keId ?? 0), 'knowledge_base_id' => $kbId];
    }
}

if (!function_exists('itm_known_error_score_row')) {
    function itm_known_error_score_row(array $row, array $tokens)
    {
        if (empty($tokens)) {
            return 0;
        }
        $haystack = strtolower(
            (string)($row['ke_title'] ?? '') . ' ' .
            (string)($row['workaround'] ?? '') . ' ' .
            (string)($row['symptom_keywords'] ?? '') . ' ' .
            (string)($row['problem_title'] ?? '')
        );
        $score = 0;
        foreach ($tokens as $token) {
            if ($token !== '' && strpos($haystack, $token) !== false) {
                $score++;
            }
        }
        $keywordCsv = strtolower((string)($row['symptom_keywords'] ?? ''));
        foreach ($tokens as $token) {
            if ($token !== '' && $keywordCsv !== '' && strpos($keywordCsv, $token) !== false) {
                $score += 2;
            }
        }
        return $score;
    }
}

if (!function_exists('itm_known_error_suggest_for_ticket')) {
    function itm_known_error_suggest_for_ticket($conn, $companyId, $title, $description, $limit = 5)
    {
        return itm_known_error_search_internal($conn, (int)$companyId, (string)$title . ' ' . (string)$description, (int)$limit);
    }
}

if (!function_exists('itm_known_error_search_for_query')) {
    function itm_known_error_search_for_query($conn, $companyId, $query, $limit = 3)
    {
        return itm_known_error_search_internal($conn, (int)$companyId, (string)$query, (int)$limit);
    }
}

if (!function_exists('itm_known_error_search_internal')) {
    function itm_known_error_search_internal($conn, $companyId, $text, $limit = 5)
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $companyId = (int)$companyId;
        $limit = max(1, min(10, (int)$limit));
        $tokens = itm_problem_tokenize_search_text($text, 4);
        if ($companyId <= 0 || empty($tokens)) {
            return [];
        }

        $likeParts = [];
        $types = 'i';
        $params = [$companyId];
        foreach (array_slice($tokens, 0, 8) as $token) {
            $like = '%' . $token . '%';
            $likeParts[] = '(ke.title LIKE ? OR ke.workaround LIKE ? OR ke.symptom_keywords LIKE ? OR p.title LIKE ?)';
            $types .= 'ssss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $whereLike = implode(' OR ', $likeParts);
        $sql = "SELECT ke.id AS known_error_id, ke.problem_id, ke.title AS ke_title, ke.workaround, ke.symptom_keywords,
                       ke.knowledge_base_id, p.title AS problem_title, p.status AS problem_status
                FROM known_errors ke
                INNER JOIN problems p ON p.id = ke.problem_id AND p.company_id = ke.company_id
                WHERE ke.company_id = ?
                  AND ke.deleted_at IS NULL AND ke.active = 1
                  AND p.deleted_at IS NULL AND p.active = 1
                  AND p.status IN ('known_error', 'investigating')
                  AND ({$whereLike})
                ORDER BY ke.id DESC
                LIMIT 50";

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
        $candidates = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $score = itm_known_error_score_row($row, $tokens);
            if ($score >= 1) {
                $row['match_score'] = $score;
                $candidates[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        usort($candidates, static function ($a, $b) {
            return (int)($b['match_score'] ?? 0) <=> (int)($a['match_score'] ?? 0);
        });

        return array_slice($candidates, 0, $limit);
    }
}

if (!function_exists('itm_problem_management_summary')) {
    function itm_problem_management_summary($conn, $companyId)
    {
        if (!$conn instanceof mysqli) {
            return [
                'investigating' => 0,
                'known_error' => 0,
                'resolved' => 0,
                'closed' => 0,
                'linked_incidents' => 0,
                'closed_this_month' => 0,
            ];
        }
        $companyId = (int)$companyId;
        $summary = [
            'investigating' => 0,
            'known_error' => 0,
            'resolved' => 0,
            'closed' => 0,
            'linked_incidents' => 0,
            'closed_this_month' => 0,
        ];
        $stmt = mysqli_prepare(
            $conn,
            "SELECT status, COUNT(*) AS c FROM problems WHERE company_id = ? AND deleted_at IS NULL GROUP BY status"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $key = (string)($row['status'] ?? '');
                if (isset($summary[$key])) {
                    $summary[$key] = (int)($row['c'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
        }
        $linkRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM problem_ticket_links WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL');
        if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
            $summary['linked_incidents'] = (int)($linkRow['c'] ?? 0);
        }
        $monthStart = date('Y-m-01 00:00:00');
        $cStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS c FROM problems WHERE company_id = ? AND status = 'closed' AND deleted_at IS NULL AND resolved_at >= ?"
        );
        if ($cStmt) {
            mysqli_stmt_bind_param($cStmt, 'is', $companyId, $monthStart);
            mysqli_stmt_execute($cStmt);
            $cRes = mysqli_stmt_get_result($cStmt);
            if ($cRes && ($cRow = mysqli_fetch_assoc($cRes))) {
                $summary['closed_this_month'] = (int)($cRow['c'] ?? 0);
            }
            mysqli_stmt_close($cStmt);
        }
        return $summary;
    }
}
