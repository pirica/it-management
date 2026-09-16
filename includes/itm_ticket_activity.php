<?php
/**
 * Append-only ticket timeline events and unified Activity feed helpers.
 */

if (!function_exists('itm_ticket_activity_log')) {
    function itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, $eventType, $payload = [])
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = $actorEmployeeId !== null ? (int)$actorEmployeeId : null;
        $eventType = trim((string)$eventType);
        if ($companyId <= 0 || $ticketId <= 0 || $eventType === '') {
            return false;
        }

        $payloadJson = json_encode(is_array($payload) ? $payload : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sessionEmployee = (int)($_SESSION['employee_id'] ?? 0);

        $sql = 'INSERT INTO ticket_activity (company_id, ticket_id, actor_employee_id, event_type, payload_json, created_by)
                VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiissi', $companyId, $ticketId, $actorEmployeeId, $eventType, $payloadJson, $sessionEmployee);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_activity_employee_label')) {
    function itm_ticket_activity_employee_label($conn, $companyId, $employeeId)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return 'Unassigned';
        }
        $sql = 'SELECT first_name, last_name, username FROM employees WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return '#' . $employeeId;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return '#' . $employeeId;
        }
        $full = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $username = trim((string)($row['username'] ?? ''));
        return $username !== '' ? $username : '#' . $employeeId;
    }
}

if (!function_exists('itm_ticket_activity_actor_display_name')) {
    function itm_ticket_activity_actor_display_name(array $activityRow)
    {
        $full = trim((string)($activityRow['actor_first_name'] ?? '') . ' ' . (string)($activityRow['actor_last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $username = trim((string)($activityRow['actor_username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
        return 'System';
    }
}

if (!function_exists('itm_ticket_activity_for_ticket')) {
    function itm_ticket_activity_for_ticket($conn, $companyId, $ticketId)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $rows = [];
        $sql = 'SELECT ta.*, e.first_name AS actor_first_name, e.last_name AS actor_last_name, e.username AS actor_username
                FROM ticket_activity ta
                LEFT JOIN employees e ON e.id = ta.actor_employee_id
                WHERE ta.company_id = ? AND ta.ticket_id = ? AND ta.deleted_at IS NULL AND ta.active = 1
                ORDER BY ta.created_at ASC';
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

if (!function_exists('itm_ticket_activity_format_event_summary')) {
    function itm_ticket_activity_format_event_summary($eventType, $payload)
    {
        $eventType = trim((string)$eventType);
        if (!is_array($payload)) {
            $payload = [];
        }

        switch ($eventType) {
            case 'status_changed':
                $from = trim((string)($payload['from_status_name'] ?? ''));
                $to = trim((string)($payload['to_status_name'] ?? ''));
                if ($from !== '' && $to !== '') {
                    return 'Status changed from ' . $from . ' to ' . $to;
                }
                return 'Status changed';
            case 'priority_changed':
                $from = trim((string)($payload['from_priority_name'] ?? ''));
                $to = trim((string)($payload['to_priority_name'] ?? ''));
                if ($from !== '' && $to !== '') {
                    return 'Priority changed from ' . $from . ' to ' . $to;
                }
                return 'Priority changed';
            case 'assigned':
                $toName = trim((string)($payload['to_employee_name'] ?? ''));
                if ($toName === '' || $toName === 'Unassigned') {
                    return 'Ticket unassigned';
                }
                return 'Assigned to ' . $toName;
            case 'archived':
                return 'Ticket archived';
            case 'unarchived':
                return 'Ticket restored from archive';
            case 'comment_added':
                $preview = trim((string)($payload['body_preview'] ?? ''));
                if ((int)($payload['is_internal'] ?? 0) === 1) {
                    return $preview !== '' ? 'Internal note: ' . $preview : 'Internal note added';
                }
                return $preview !== '' ? 'Comment: ' . $preview : 'Comment added';
            case 'problem_linked':
                return 'Problem #' . (int)($payload['problem_id'] ?? 0) . ' linked';
            case 'problem_unlinked':
                return 'Problem #' . (int)($payload['problem_id'] ?? 0) . ' unlinked';
            case 'known_error_applied':
                return 'Known error workaround applied';
            case 'ticket_merged':
                return 'Merged ticket #' . (int)($payload['source_ticket_id'] ?? 0) . ' into this ticket';
            case 'ticket_merged_into':
                return 'Merged into ticket #' . (int)($payload['target_ticket_id'] ?? 0);
            case 'ticket_reopened':
                return 'Ticket reopened';
            case 'survey_issued':
                return 'Customer survey issued';
            case 'survey_completed':
                return 'Customer survey completed';
            case 'csat_submitted':
                return 'CSAT score submitted';
            case 'sla_response_breached':
                return 'SLA response breached';
            case 'sla_resolve_breached':
                return 'SLA resolution breached';
            case 'live_chat_started':
                return 'Live chat started';
            case 'live_chat_claimed':
                return 'Live chat claimed';
            case 'live_chat_closed':
                return 'Live chat closed';
            case 'live_chat_rated':
                return 'Live chat rated';
            default:
                $label = str_replace('_', ' ', $eventType);
                return ucfirst($label);
        }
    }
}

if (!function_exists('itm_ticket_unified_activity_feed')) {
    /**
     * Chronological Activity feed: comment rows + system events (excludes comment_added duplicates).
     *
     * @return array<int, array{kind:string,sort_at:string,comment?:array,event?:array}>
     */
    function itm_ticket_unified_activity_feed($conn, $companyId, $ticketId, $viewerEmployeeId, $viewerIsSupportAgent)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $items = [];

        if (!function_exists('itm_ticket_comments_for_ticket')) {
            require_once ROOT_PATH . 'includes/itm_ticket_comments.php';
        }

        foreach (itm_ticket_comments_for_ticket($conn, $companyId, $ticketId, (int)$viewerEmployeeId, (bool)$viewerIsSupportAgent) as $commentRow) {
            $sortAt = (string)($commentRow['created_at'] ?? '');
            $items[] = [
                'kind' => 'comment',
                'sort_at' => $sortAt,
                'comment' => $commentRow,
            ];
        }

        foreach (itm_ticket_activity_for_ticket($conn, $companyId, $ticketId) as $eventRow) {
            if ((string)($eventRow['event_type'] ?? '') === 'comment_added') {
                continue;
            }
            $items[] = [
                'kind' => 'event',
                'sort_at' => (string)($eventRow['created_at'] ?? ''),
                'event' => $eventRow,
            ];
        }

        usort($items, static function ($a, $b) {
            $ta = strtotime((string)($a['sort_at'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['sort_at'] ?? '')) ?: 0;
            if ($ta === $tb) {
                return 0;
            }
            return $ta < $tb ? -1 : 1;
        });

        return $items;
    }
}

if (!function_exists('itm_ticket_log_edit_field_changes')) {
    function itm_ticket_log_edit_field_changes($conn, $companyId, $ticketId, $actorEmployeeId, array $previous, array $current)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = (int)$actorEmployeeId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return;
        }

        $prevStatusId = (int)($previous['status_id'] ?? 0);
        $newStatusId = (int)($current['status_id'] ?? 0);
        if ($newStatusId > 0 && $newStatusId !== $prevStatusId) {
            $fromName = $prevStatusId > 0 && function_exists('itm_automation_rules_resolve_ticket_status_name')
                ? itm_automation_rules_resolve_ticket_status_name($conn, $companyId, $prevStatusId)
                : '';
            $toName = function_exists('itm_automation_rules_resolve_ticket_status_name')
                ? itm_automation_rules_resolve_ticket_status_name($conn, $companyId, $newStatusId)
                : '';
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'status_changed', [
                'from_status_id' => $prevStatusId,
                'to_status_id' => $newStatusId,
                'from_status_name' => $fromName,
                'to_status_name' => $toName,
            ]);
        }

        $prevPriorityId = (int)($previous['priority_id'] ?? 0);
        $newPriorityId = (int)($current['priority_id'] ?? 0);
        if ($newPriorityId > 0 && $newPriorityId !== $prevPriorityId) {
            $fromName = $prevPriorityId > 0 && function_exists('itm_automation_rules_resolve_ticket_priority_name')
                ? itm_automation_rules_resolve_ticket_priority_name($conn, $companyId, $prevPriorityId)
                : '';
            $toName = function_exists('itm_automation_rules_resolve_ticket_priority_name')
                ? itm_automation_rules_resolve_ticket_priority_name($conn, $companyId, $newPriorityId)
                : '';
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'priority_changed', [
                'from_priority_id' => $prevPriorityId,
                'to_priority_id' => $newPriorityId,
                'from_priority_name' => $fromName,
                'to_priority_name' => $toName,
            ]);
        }

        $prevAssigneeId = (int)($previous['assigned_to_employee_id'] ?? 0);
        $newAssigneeId = (int)($current['assigned_to_employee_id'] ?? 0);
        if ($newAssigneeId !== $prevAssigneeId) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'assigned', [
                'from_employee_id' => $prevAssigneeId,
                'to_employee_id' => $newAssigneeId,
                'from_employee_name' => itm_ticket_activity_employee_label($conn, $companyId, $prevAssigneeId),
                'to_employee_name' => itm_ticket_activity_employee_label($conn, $companyId, $newAssigneeId),
            ]);
        }
    }
}

if (!function_exists('itm_ticket_activity_render_feed_item_html')) {
    function itm_ticket_activity_render_feed_item_html(array $feedItem)
    {
        $kind = (string)($feedItem['kind'] ?? '');
        if ($kind === 'comment') {
            $tc = is_array($feedItem['comment'] ?? null) ? $feedItem['comment'] : [];
            $author = trim((string)($tc['first_name'] ?? '') . ' ' . (string)($tc['last_name'] ?? ''));
            if ($author === '') {
                $author = (string)($tc['username'] ?? '');
            }
            $bodyHtml = nl2br(sanitize((string)($tc['body'] ?? '')));
            $photosHtml = '';
            if (function_exists('itm_ticket_comment_render_photos_html')) {
                $photosHtml = itm_ticket_comment_render_photos_html($tc);
            }
            $internalBadge = (int)($tc['is_internal'] ?? 0) === 1
                ? '<span class="badge">Internal</span>'
                : '';
            $timestamp = sanitize(itm_format_audit_timestamp_display($tc['created_at'] ?? ''));
            return '<li class="itm-ticket-activity-feed-item itm-ticket-activity-feed-comment" style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border,#e5e7eb);">'
                . '<strong>' . sanitize($author) . '</strong> ' . $internalBadge
                . '<div>' . $bodyHtml . '</div>'
                . $photosHtml
                . '<small>' . $timestamp . '</small>'
                . '</li>';
        }
        if ($kind === 'event') {
            $ev = is_array($feedItem['event'] ?? null) ? $feedItem['event'] : [];
            $payload = json_decode((string)($ev['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $summary = itm_ticket_activity_format_event_summary((string)($ev['event_type'] ?? ''), $payload);
            $actor = sanitize(itm_ticket_activity_actor_display_name($ev));
            $timestamp = sanitize(itm_format_audit_timestamp_display($ev['created_at'] ?? ''));
            return '<li class="itm-ticket-activity-feed-item itm-ticket-activity-feed-event" style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px dashed var(--border,#e5e7eb);">'
                . '<span class="badge badge-secondary" style="margin-right:6px;">System</span> '
                . '<strong>' . $actor . '</strong> '
                . '<span>' . sanitize($summary) . '</span>'
                . '<div><small>' . $timestamp . '</small></div>'
                . '</li>';
        }
        return '';
    }
}
