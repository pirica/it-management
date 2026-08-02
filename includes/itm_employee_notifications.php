<?php
/**
 * Per-employee in-app notification inbox (metadata only — no vault/plaintext private content).
 */

if (!function_exists('itm_employee_notification_build_action_url')) {
    function itm_employee_notification_build_action_url($moduleSlug, $recordId = null)
    {
        $slug = trim((string)$moduleSlug);
        if ($slug === '') {
            return null;
        }
        $base = defined('BASE_URL') ? BASE_URL : '/';
        if ($slug === 'live_chat_conversations' && $recordId !== null && (int)$recordId > 0) {
            return $base . 'modules/live_chat/?conversation_id=' . (int)$recordId;
        }
        $path = 'modules/' . $slug . '/';
        if ($recordId !== null && (int)$recordId > 0) {
            $path .= 'view.php?id=' . (int)$recordId;
        } else {
            $path .= 'index.php';
        }
        return $base . $path;
    }
}

if (!function_exists('itm_employee_resolve_id_by_email')) {
    function itm_employee_resolve_id_by_email($conn, $companyId, $email)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $companyId = (int)$companyId;
        $email = strtolower(trim((string)$email));
        if ($companyId <= 0 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        $sql = 'SELECT id FROM employees
                WHERE company_id = ? AND deleted_at IS NULL AND active = 1
                  AND (
                    LOWER(TRIM(COALESCE(work_email, \'\'))) = ?
                    OR LOWER(TRIM(COALESCE(personal_email, \'\'))) = ?
                    OR LOWER(TRIM(COALESCE(email, \'\'))) = ?
                  )
                LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'isss', $companyId, $email, $email, $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_employee_resolve_ids_from_address_list')) {
    /**
     * @return array<int, int>
     */
    function itm_employee_resolve_ids_from_address_list($conn, $companyId, $addressList, $excludeEmployeeId = 0)
    {
        if (!function_exists('itm_email_parse_address_list')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }
        $excludeEmployeeId = (int)$excludeEmployeeId;
        $ids = [];
        foreach (itm_email_parse_address_list($addressList) as $email) {
            $employeeId = itm_employee_resolve_id_by_email($conn, $companyId, $email);
            if ($employeeId > 0 && $employeeId !== $excludeEmployeeId) {
                $ids[$employeeId] = $employeeId;
            }
        }
        return array_values($ids);
    }
}

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

if (!function_exists('itm_employee_notification_has_recent_duplicate')) {
    function itm_employee_notification_has_recent_duplicate($conn, $companyId, $employeeId, $moduleSlug, $recordId, $title)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $recordId = $recordId !== null ? (int)$recordId : null;
        $moduleSlug = trim((string)$moduleSlug);
        $title = trim((string)$title);
        if ($companyId <= 0 || $employeeId <= 0 || $moduleSlug === '' || $title === '') {
            return false;
        }
        $sql = 'SELECT id FROM employee_notifications
                WHERE company_id = ? AND employee_id = ? AND module_slug = ? AND title = ?
                  AND is_read = 0 AND deleted_at IS NULL AND active = 1
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
        if ($recordId !== null && $recordId > 0) {
            $sql .= ' AND record_id = ?';
        } else {
            $sql .= ' AND record_id IS NULL';
        }
        $sql .= ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        if ($recordId !== null && $recordId > 0) {
            mysqli_stmt_bind_param($stmt, 'iissi', $companyId, $employeeId, $moduleSlug, $title, $recordId);
        } else {
            mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $employeeId, $moduleSlug, $title);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return (bool)$exists;
    }
}

if (!function_exists('itm_notify_employee')) {
    /**
     * Central in-app notification dispatcher.
     *
     * @param array $payload company_id, module_slug, record_id?, title, body?, action_url?
     */
    function itm_notify_employee($conn, $employeeId, array $payload)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || !$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)($payload['company_id'] ?? ($_SESSION['company_id'] ?? 0));
        $moduleSlug = trim((string)($payload['module_slug'] ?? ''));
        $recordId = isset($payload['record_id']) && $payload['record_id'] !== '' && $payload['record_id'] !== null
            ? (int)$payload['record_id'] : null;
        $title = trim((string)($payload['title'] ?? ''));
        $body = array_key_exists('body', $payload) ? $payload['body'] : null;
        $actionUrl = array_key_exists('action_url', $payload) ? $payload['action_url'] : null;
        if ($companyId <= 0 || $moduleSlug === '' || $title === '') {
            return false;
        }
        if ($actionUrl === null || trim((string)$actionUrl) === '') {
            $actionUrl = itm_employee_notification_build_action_url($moduleSlug, $recordId);
        }
        if (itm_employee_notification_has_recent_duplicate($conn, $companyId, $employeeId, $moduleSlug, $recordId, $title)) {
            return true;
        }
        return itm_employee_notification_create($conn, $companyId, $employeeId, $moduleSlug, $recordId, $title, $body, $actionUrl);
    }
}

if (!function_exists('itm_notify_employees')) {
    /**
     * @param array<int, int> $employeeIds
     */
    function itm_notify_employees($conn, array $employeeIds, array $payload, $excludeEmployeeId = 0)
    {
        $excludeEmployeeId = (int)$excludeEmployeeId;
        $sent = 0;
        foreach ($employeeIds as $employeeId) {
            $employeeId = (int)$employeeId;
            if ($employeeId <= 0 || $employeeId === $excludeEmployeeId) {
                continue;
            }
            if (itm_notify_employee($conn, $employeeId, $payload)) {
                $sent++;
            }
        }
        return $sent;
    }
}

if (!function_exists('itm_notify_employee_ids_from_csv')) {
    function itm_notify_employee_ids_from_csv($conn, $companyId, $csv, array $payload, $excludeEmployeeId = 0)
    {
        $ids = [];
        foreach (array_filter(array_map('intval', explode(',', (string)$csv))) as $employeeId) {
            if ($employeeId > 0) {
                $ids[$employeeId] = $employeeId;
            }
        }
        return itm_notify_employees($conn, array_values($ids), $payload, $excludeEmployeeId);
    }
}

if (!function_exists('itm_notify_ticket_assigned')) {
    function itm_notify_ticket_assigned($conn, $companyId, $assigneeEmployeeId, $ticketId, $ticketTitle, $ticketCode = '')
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $ticketId = (int)$ticketId;
        if ($assigneeEmployeeId <= 0 || $ticketId <= 0) {
            return false;
        }
        $label = trim((string)$ticketTitle);
        if ($ticketCode !== '') {
            $label = trim((string)$ticketCode) . ($label !== '' ? ': ' . $label : '');
        }
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'tickets',
            'record_id' => $ticketId,
            'title' => 'Ticket assigned to you',
            'body' => $label !== '' ? $label : 'Open the ticket for details.',
            'action_url' => itm_employee_notification_build_action_url('tickets', $ticketId),
        ]);
    }
}

if (!function_exists('itm_notify_onboarding_approval_needed')) {
    function itm_notify_onboarding_approval_needed($conn, $companyId, $approverEmployeeId, $recordId, $approvalLabel, $employeeName)
    {
        $approverEmployeeId = (int)$approverEmployeeId;
        $recordId = (int)$recordId;
        if ($approverEmployeeId <= 0 || $recordId <= 0) {
            return false;
        }
        $employeeName = trim((string)$employeeName);
        if ($employeeName === '') {
            $employeeName = 'Employee #' . $recordId;
        }
        return itm_notify_employee($conn, $approverEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'employee_onboarding_requests',
            'record_id' => $recordId,
            'title' => 'Onboarding approval needed',
            'body' => trim((string)$approvalLabel) . ' — ' . $employeeName,
            'action_url' => itm_employee_notification_build_action_url('employee_onboarding_requests', $recordId),
        ]);
    }
}

if (!function_exists('itm_notify_warranty_expiring')) {
    function itm_notify_warranty_expiring($conn, $companyId, $assigneeEmployeeId, $equipmentId, $equipmentLabel, $warrantyExpiry)
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $equipmentId = (int)$equipmentId;
        if ($assigneeEmployeeId <= 0 || $equipmentId <= 0) {
            return false;
        }
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'equipment',
            'record_id' => $equipmentId,
            'title' => 'Warranty expiring soon',
            'body' => trim((string)$equipmentLabel) . ' — ' . trim((string)$warrantyExpiry),
            'action_url' => itm_employee_notification_build_action_url('equipment', $equipmentId),
        ]);
    }
}

if (!function_exists('itm_notify_todo_assigned')) {
    function itm_notify_todo_assigned($conn, $companyId, $assigneeCsv, $taskId, $taskTitle, $actorEmployeeId = 0)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return 0;
        }
        return itm_notify_employee_ids_from_csv($conn, (int)$companyId, $assigneeCsv, [
            'company_id' => (int)$companyId,
            'module_slug' => 'todo',
            'record_id' => $taskId,
            'title' => 'Task assigned to you',
            'body' => trim((string)$taskTitle) !== '' ? trim((string)$taskTitle) : 'Open the task for details.',
            'action_url' => itm_employee_notification_build_action_url('todo', $taskId),
        ], 0);
    }
}

if (!function_exists('itm_notify_event_assigned')) {
    function itm_notify_event_assigned($conn, $companyId, $assigneeEmployeeId, $eventId, $eventTitle, $actorEmployeeId = 0)
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $eventId = (int)$eventId;
        if ($assigneeEmployeeId <= 0 || $eventId <= 0) {
            return false;
        }
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'events',
            'record_id' => $eventId,
            'title' => 'Event assigned to you',
            'body' => trim((string)$eventTitle) !== '' ? trim((string)$eventTitle) : 'Open the event for details.',
            'action_url' => itm_employee_notification_build_action_url('events', $eventId),
        ]);
    }
}

if (!function_exists('itm_notify_alert_assigned')) {
    function itm_notify_alert_assigned($conn, $companyId, $assigneeEmployeeId, $alertId, $alertTitle, $actorEmployeeId = 0)
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $alertId = (int)$alertId;
        if ($assigneeEmployeeId <= 0 || $alertId <= 0) {
            return false;
        }
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'alerts',
            'record_id' => $alertId,
            'title' => 'Alert assigned to you',
            'body' => trim((string)$alertTitle) !== '' ? trim((string)$alertTitle) : 'Open the alert for details.',
            'action_url' => itm_employee_notification_build_action_url('alerts', $alertId),
        ]);
    }
}

if (!function_exists('itm_notify_appointment_assigned')) {
    function itm_notify_appointment_assigned($conn, $companyId, $assigneeEmployeeId, $appointmentId, $summary = '', $actorEmployeeId = 0)
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $appointmentId = (int)$appointmentId;
        if ($assigneeEmployeeId <= 0 || $appointmentId <= 0) {
            return false;
        }
        $summary = trim((string)$summary);
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'appointment',
            'record_id' => $appointmentId,
            'title' => 'Appointment assigned to you',
            'body' => $summary !== '' ? $summary : 'Open the appointment for details.',
            'action_url' => itm_employee_notification_build_action_url('appointment', $appointmentId),
        ]);
    }
}

if (!function_exists('itm_notify_live_chat_conversation_assigned')) {
    function itm_notify_live_chat_conversation_assigned($conn, $companyId, $assigneeEmployeeId, $conversationId, $summary = '', $actorEmployeeId = 0)
    {
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        $conversationId = (int)$conversationId;
        if ($assigneeEmployeeId <= 0 || $conversationId <= 0) {
            return false;
        }
        $summary = trim((string)$summary);
        return itm_notify_employee($conn, $assigneeEmployeeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'live_chat_conversations',
            'record_id' => $conversationId,
            'title' => 'Live chat assigned to you',
            'body' => $summary !== '' ? $summary : 'Open the conversation to respond.',
            'action_url' => itm_employee_notification_build_action_url('live_chat_conversations', $conversationId),
        ]);
    }
}

if (!function_exists('itm_notify_note_shared')) {
    function itm_notify_note_shared($conn, $companyId, $sharedWithJson, $noteId, $noteTitle, $ownerEmployeeId = 0)
    {
        $noteId = (int)$noteId;
        if ($noteId <= 0) {
            return 0;
        }
        $sharedIds = json_decode((string)$sharedWithJson, true);
        if (!is_array($sharedIds) || $sharedIds === []) {
            return 0;
        }
        $employeeIds = [];
        foreach ($sharedIds as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && $uid !== (int)$ownerEmployeeId) {
                $employeeIds[$uid] = $uid;
            }
        }
        return itm_notify_employees($conn, array_values($employeeIds), [
            'company_id' => (int)$companyId,
            'module_slug' => 'notes',
            'record_id' => $noteId,
            'title' => 'Note shared with you',
            'body' => trim((string)$noteTitle) !== '' ? trim((string)$noteTitle) : 'Open the note for details.',
            'action_url' => itm_employee_notification_build_action_url('notes', $noteId),
        ], (int)$ownerEmployeeId);
    }
}

if (!function_exists('itm_notify_email_logged')) {
    function itm_notify_email_logged($conn, $companyId, $emailId, $toEmail, $ccEmail, $subject, $excludeEmployeeId = 0)
    {
        $companyId = (int)$companyId;
        $emailId = (int)$emailId;
        if ($companyId <= 0) {
            return 0;
        }
        $employeeIds = itm_employee_resolve_ids_from_address_list($conn, $companyId, $toEmail, (int)$excludeEmployeeId);
        foreach (itm_employee_resolve_ids_from_address_list($conn, $companyId, $ccEmail, (int)$excludeEmployeeId) as $ccId) {
            $employeeIds[$ccId] = $ccId;
        }
        $subject = trim((string)$subject);
        return itm_notify_employees($conn, array_values($employeeIds), [
            'company_id' => $companyId,
            'module_slug' => 'emails',
            'record_id' => $emailId > 0 ? $emailId : null,
            'title' => 'Email addressed to you',
            'body' => $subject !== '' ? $subject : 'Open Email Management send logs.',
            'action_url' => $emailId > 0
                ? itm_employee_notification_build_action_url('emails', $emailId)
                : itm_employee_notification_build_action_url('emails', null),
        ], (int)$excludeEmployeeId);
    }
}

if (!function_exists('itm_ticket_comment_extract_mention_usernames')) {
    function itm_ticket_comment_extract_mention_usernames($body)
    {
        $body = (string)$body;
        if ($body === '' || !preg_match_all('/@([a-zA-Z0-9_\.\-]{1,64})/', $body, $matches)) {
            return [];
        }
        $usernames = [];
        foreach (array_unique(array_map('strtolower', $matches[1])) as $username) {
            $usernames[$username] = $username;
        }
        return array_values($usernames);
    }
}

if (!function_exists('itm_notify_ticket_comment_mentions')) {
    function itm_notify_ticket_comment_mentions($conn, $companyId, $ticketId, $commentId, $body, $authorEmployeeId = 0, $previousBody = null)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $commentId = (int)$commentId;
        $authorEmployeeId = (int)$authorEmployeeId;
        $body = (string)$body;
        if ($companyId <= 0 || $ticketId <= 0 || $body === '') {
            return 0;
        }
        $usernames = itm_ticket_comment_extract_mention_usernames($body);
        if ($previousBody !== null) {
            $previousUsernames = itm_ticket_comment_extract_mention_usernames($previousBody);
            if ($previousUsernames !== []) {
                $previousLookup = array_fill_keys($previousUsernames, true);
                $usernames = array_values(array_filter($usernames, static function ($username) use ($previousLookup) {
                    return !isset($previousLookup[$username]);
                }));
            }
        }
        if ($usernames === []) {
            return 0;
        }
        $notified = 0;
        foreach ($usernames as $username) {
            $sql = 'SELECT id FROM employees WHERE company_id = ? AND deleted_at IS NULL AND active = 1 AND LOWER(username) = ? LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $username);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $mentionedId = (int)($row['id'] ?? 0);
            if ($mentionedId <= 0 || $mentionedId === $authorEmployeeId) {
                continue;
            }
            if (itm_notify_employee($conn, $mentionedId, [
                'company_id' => $companyId,
                'module_slug' => 'tickets',
                'record_id' => $ticketId,
                'title' => 'You were mentioned on a ticket',
                'body' => '@' . $username . ' in comment #' . $commentId,
                'action_url' => itm_employee_notification_build_action_url('tickets', $ticketId),
            ])) {
                $notified++;
            }
        }
        return $notified;
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

if (!function_exists('itm_employee_notifications_sse_stream')) {
    /**
     * Server-Sent Events stream for header bell unread count (short-lived; client reconnects).
     */
    function itm_employee_notifications_sse_stream($conn, $companyId, $employeeId, $maxSeconds = 55, $pollSeconds = 5)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            http_response_code(401);
            return;
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $lastUnread = -1;
        $started = time();
        $maxSeconds = max(10, min(120, (int)$maxSeconds));
        $pollSeconds = max(2, min(30, (int)$pollSeconds));

        while ((time() - $started) < $maxSeconds) {
            if (connection_aborted()) {
                break;
            }
            $unread = itm_employee_notification_unread_count($conn, $companyId, $employeeId);
            if ($unread !== $lastUnread) {
                $payload = json_encode([
                    'ok' => true,
                    'unread_count' => $unread,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo 'event: unread' . "\n";
                echo 'data: ' . $payload . "\n\n";
                $lastUnread = $unread;
            } else {
                echo ": heartbeat\n\n";
            }
            if (function_exists('flush')) {
                flush();
            }
            sleep($pollSeconds);
        }
        exit;
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

if (!function_exists('itm_employee_notifications_send_digest_emails')) {
    /**
     * Sends a daily-style digest of unread in-app notifications to each employee with a resolvable work email.
     *
     * @return array{employees:int,sent:int,skipped:int}
     */
    function itm_employee_notifications_send_digest_emails($conn, $companyId = 0)
    {
        if (!$conn instanceof mysqli) {
            return ['employees' => 0, 'sent' => 0, 'skipped' => 0];
        }
        if (!function_exists('itm_send_email')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }
        $companyFilter = (int)$companyId;
        $companies = [];
        if ($companyFilter > 0) {
            $companies[] = $companyFilter;
        } else {
            $res = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1 ORDER BY id ASC');
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $companies[] = (int)$row['id'];
            }
        }
        $stats = ['employees' => 0, 'sent' => 0, 'skipped' => 0];
        foreach ($companies as $cid) {
            $sql = 'SELECT e.id AS employee_id,
                           COALESCE(NULLIF(TRIM(e.work_email), \'\'), NULLIF(TRIM(e.personal_email), \'\'), NULLIF(TRIM(e.email), \'\')) AS deliver_email,
                           COUNT(n.id) AS unread_count
                    FROM employees e
                    INNER JOIN employee_notifications n
                        ON n.company_id = e.company_id AND n.employee_id = e.id
                       AND n.is_read = 0 AND n.deleted_at IS NULL AND n.active = 1
                    WHERE e.company_id = ? AND e.deleted_at IS NULL AND e.active = 1
                    GROUP BY e.id, deliver_email
                    HAVING unread_count > 0';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'i', $cid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $stats['employees']++;
                $email = trim((string)($row['deliver_email'] ?? ''));
                $employeeId = (int)($row['employee_id'] ?? 0);
                $unreadCount = (int)($row['unread_count'] ?? 0);
                if ($email === '' || $employeeId <= 0 || $unreadCount <= 0) {
                    $stats['skipped']++;
                    continue;
                }
                $items = itm_employee_notifications_list_recent($conn, $cid, $employeeId, 10);
                $lines = [];
                foreach ($items as $item) {
                    if ((int)($item['is_read'] ?? 0) === 1) {
                        continue;
                    }
                    $lines[] = '<li><strong>' . htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>'
                        . (trim((string)($item['body'] ?? '')) !== ''
                            ? ' — ' . htmlspecialchars((string)$item['body'], ENT_QUOTES, 'UTF-8') : '');
                }
                if ($lines === []) {
                    $stats['skipped']++;
                    continue;
                }
                $inboxUrl = itm_employee_notification_build_action_url('employee_notifications', null);
                $html = '<p>You have <strong>' . $unreadCount . '</strong> unread notification(s):</p><ul>'
                    . implode('', $lines) . '</ul>'
                    . '<p><a href="' . htmlspecialchars((string)$inboxUrl, ENT_QUOTES, 'UTF-8') . '">Open notification inbox</a></p>';
                $subject = 'IT Management — ' . $unreadCount . ' unread notification' . ($unreadCount === 1 ? '' : 's');
                if (itm_send_email($email, $subject, $html, $cid, ['email_template' => ['subtitle' => 'Notification digest']])) {
                    $stats['sent']++;
                } else {
                    $stats['skipped']++;
                }
            }
            mysqli_stmt_close($stmt);
        }
        return $stats;
    }
}
