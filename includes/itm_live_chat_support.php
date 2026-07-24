<?php
/**
 * Live chat support-agent detection and conversation ACL.
 */

if (!function_exists('itm_live_chat_employee_homed_in_company')) {
    /**
     * Why: Chat-with peers must belong to the active tenant (home company_id), not cross-grant visitors.
     */
    function itm_live_chat_employee_homed_in_company($conn, $employeeId, $companyId)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        if (!$conn instanceof mysqli || $employeeId <= 0 || $companyId <= 0) {
            return false;
        }
        $sql = 'SELECT 1 FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_live_chat_peer_eligible_for_company')) {
    function itm_live_chat_peer_eligible_for_company($conn, $employeeId, $companyId)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        if ($employeeId <= 0 || $companyId <= 0) {
            return false;
        }
        if (itm_it_settings_chat_same_tenant_enabled($conn, $companyId)) {
            return itm_live_chat_employee_homed_in_company($conn, $employeeId, $companyId);
        }
        $label = itm_user_label_by_id_for_company($conn, $companyId, $employeeId);
        return $label !== '';
    }
}

if (!function_exists('itm_live_chat_peer_options_for_company')) {
    /**
     * @return array<int, array{id:int,label:string}>
     */
    function itm_live_chat_peer_options_for_company($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return [];
        }
        if (!itm_it_settings_chat_same_tenant_enabled($conn, $companyId)) {
            return itm_user_options_for_company($conn, $companyId);
        }
        $options = [];
        $sql = 'SELECT id, username, first_name, last_name FROM employees
                WHERE company_id = ? AND deleted_at IS NULL AND active = 1
                ORDER BY first_name ASC, last_name ASC, username ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $options;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $label = $fullName !== '' ? $fullName : (trim((string)($row['username'] ?? '')) ?: ('User #' . (int)$row['id']));
            $options[] = ['id' => (int)$row['id'], 'label' => $label];
        }
        mysqli_stmt_close($stmt);
        return $options;
    }
}

if (!function_exists('itm_live_chat_employee_in_it_department')) {
    function itm_live_chat_employee_in_it_department($conn, $employeeId, $companyId)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        if (!$conn instanceof mysqli || $employeeId <= 0 || $companyId <= 0) {
            return false;
        }
        $sql = 'SELECT d.name FROM employees e
                INNER JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
                WHERE e.id = ? AND e.company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row && strtolower((string)$row['name']) === 'it';
    }
}

if (!function_exists('itm_live_chat_is_support_agent')) {
    function itm_live_chat_is_support_agent($conn, $employeeId)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return false;
        }
        if (function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId)) {
            return true;
        }
        $companyId = (int)($_SESSION['company_id'] ?? 0);
        return itm_live_chat_employee_in_it_department($conn, $employeeId, $companyId);
    }
}

if (!function_exists('itm_live_chat_fetch_conversation_row')) {
    function itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId)
    {
        $sql = 'SELECT * FROM live_chat_conversations
                WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_live_chat_is_participant')) {
    function itm_live_chat_is_participant($conn, $companyId, $conversationId, $employeeId)
    {
        $sql = 'SELECT 1 FROM live_chat_participants
                WHERE conversation_id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $conversationId, $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ok = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_live_chat_can_view_conversation')) {
    function itm_live_chat_can_view_conversation($conn, $companyId, $conversationId, $employeeId)
    {
        $companyId = (int)$companyId;
        $conversationId = (int)$conversationId;
        $employeeId = (int)$employeeId;
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        if (!$conv) {
            return false;
        }
        if ((string)$conv['conversation_type'] === 'live_agent') {
            if (itm_live_chat_is_support_agent($conn, $employeeId)) {
                return true;
            }
            if ((int)$conv['requester_employee_id'] === $employeeId) {
                return true;
            }
            if ((int)$conv['assigned_to_employee_id'] === $employeeId) {
                return true;
            }
            return false;
        }
        return itm_live_chat_is_participant($conn, $companyId, $conversationId, $employeeId);
    }
}

if (!function_exists('itm_live_chat_list_conversations_sql')) {
    function itm_live_chat_list_conversations_sql($conn, $companyId, $employeeId, $isSupportAgent)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $rows = [];

        if ($isSupportAgent) {
            $sql = 'SELECT c.*, p.last_read_at,
                    (SELECT COUNT(*) FROM live_chat_messages m
                     WHERE m.conversation_id = c.id AND m.deleted_at IS NULL
                     AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)
                     AND m.sender_employee_id <> ?) AS unread_count
                    FROM live_chat_conversations c
                    LEFT JOIN live_chat_participants p ON p.conversation_id = c.id AND p.employee_id = ?
                    WHERE c.company_id = ? AND c.deleted_at IS NULL
                    AND (c.conversation_type = \'live_agent\'
                         OR EXISTS (SELECT 1 FROM live_chat_participants lp
                                    WHERE lp.conversation_id = c.id AND lp.employee_id = ? AND lp.deleted_at IS NULL))
                    ORDER BY FIELD(c.status, \'waiting\', \'active\', \'closed\'), c.updated_at DESC, c.id DESC';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return $rows;
            }
            mysqli_stmt_bind_param($stmt, 'iiii', $employeeId, $employeeId, $companyId, $employeeId);
        } else {
            $sql = 'SELECT c.*, p.last_read_at,
                    (SELECT COUNT(*) FROM live_chat_messages m
                     WHERE m.conversation_id = c.id AND m.deleted_at IS NULL
                     AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)
                     AND m.sender_employee_id <> ?) AS unread_count
                    FROM live_chat_conversations c
                    INNER JOIN live_chat_participants p ON p.conversation_id = c.id AND p.employee_id = ? AND p.deleted_at IS NULL
                    WHERE c.company_id = ? AND c.deleted_at IS NULL
                    AND (c.conversation_type = \'chat_with\'
                         OR c.requester_employee_id = ?
                         OR c.assigned_to_employee_id = ?)
                    ORDER BY c.updated_at DESC, c.id DESC';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return $rows;
            }
            mysqli_stmt_bind_param($stmt, 'iiiii', $employeeId, $employeeId, $companyId, $employeeId, $employeeId);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_live_chat_support_agent_ids')) {
    function itm_live_chat_support_agent_ids($conn, $companyId)
    {
        $companyId = (int)$companyId;
        $ids = [];
        $sql = 'SELECT e.id FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
                LEFT JOIN employee_roles er ON er.id = e.role_id
                WHERE e.company_id = ? AND e.deleted_at IS NULL AND e.active = 1
                AND (LOWER(COALESCE(d.name, \'\')) = \'it\' OR LOWER(COALESCE(er.name, \'\')) = \'admin\')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $ids;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);
        return array_values(array_unique($ids));
    }
}

if (!function_exists('itm_live_chat_notify_support_agents_waiting')) {
    function itm_live_chat_notify_support_agents_waiting($conn, $companyId, $conversationId, $title, $body)
    {
        $actionUrl = BASE_URL . 'modules/live_chat/?conversation_id=' . (int)$conversationId;
        foreach (itm_live_chat_support_agent_ids($conn, $companyId) as $agentId) {
            itm_employee_notification_create($conn, $companyId, $agentId, 'live_chat', (int)$conversationId, $title, $body, $actionUrl);
        }
    }
}

if (!function_exists('itm_live_chat_mark_read')) {
    function itm_live_chat_mark_read($conn, $companyId, $conversationId, $employeeId)
    {
        $sql = 'UPDATE live_chat_participants SET last_read_at = NOW(), updated_by = ?
                WHERE conversation_id = ? AND company_id = ? AND employee_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        $updatedBy = (int)$employeeId;
        mysqli_stmt_bind_param($stmt, 'iiii', $updatedBy, $conversationId, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}
