<?php
/**
 * Unified approval inbox — adapter registry, upsert, fetch, and decide helpers.
 * Source modules remain authoritative; inbox rows mirror pending stages per assignee.
 */

if (!function_exists('itm_approval_inbox_adapter_slugs')) {
    function itm_approval_inbox_adapter_slugs()
    {
        return ['request_password', 'employee_onboarding_requests'];
    }
}

if (!function_exists('itm_approval_inbox_map_source_status')) {
    function itm_approval_inbox_map_source_status($sourceStatus)
    {
        $normalized = strtolower(trim((string)$sourceStatus));
        if ($normalized === '' || $normalized === 'waiting' || $normalized === 'pending') {
            return 'pending';
        }
        if (in_array($normalized, ['approved', 'authorize', 'authorized'], true)) {
            return 'approved';
        }
        if (in_array($normalized, ['declined', 'rejected', 'denied'], true)) {
            return 'rejected';
        }
        if (in_array($normalized, ['cancelled', 'canceled'], true)) {
            return 'cancelled';
        }
        return 'pending';
    }
}

if (!function_exists('itm_approval_inbox_status_badge')) {
    function itm_approval_inbox_status_badge($status)
    {
        $status = strtolower(trim((string)$status));
        $class = 'badge-secondary';
        if ($status === 'pending') {
            $class = 'badge-warning';
        } elseif ($status === 'approved') {
            $class = 'badge-success';
        } elseif ($status === 'rejected') {
            $class = 'badge-danger';
        }
        $label = ucfirst($status !== '' ? $status : 'pending');
        return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('itm_approval_inbox_resolve_approver_employee_id')) {
    function itm_approval_inbox_resolve_approver_employee_id(mysqli $conn, $companyId, $approverTypeDescription, $departmentName = '')
    {
        $companyId = (int)$companyId;
        $approverTypeDescription = trim((string)$approverTypeDescription);
        $departmentName = trim((string)$departmentName);
        if ($companyId <= 0 || $approverTypeDescription === '') {
            return 0;
        }

        if ($departmentName !== '') {
            $sql = "SELECT a.employee_id FROM approvers a
                    INNER JOIN departments d ON d.id = a.department_id AND d.company_id = a.company_id
                    INNER JOIN approver_type at ON at.id = a.approver_type_id AND at.company_id = a.company_id
                    WHERE a.company_id = ? AND a.active = 1 AND d.active = 1 AND at.active = 1
                      AND d.name = ? AND at.approver_type_description = ?
                    ORDER BY a.id ASC LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iss', $companyId, $departmentName, $approverTypeDescription);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if ($row && (int)($row['employee_id'] ?? 0) > 0) {
                    return (int)$row['employee_id'];
                }
            }
        }

        $sql = "SELECT a.employee_id FROM approvers a
                INNER JOIN approver_type at ON at.id = a.approver_type_id AND at.company_id = a.company_id
                WHERE a.company_id = ? AND a.active = 1 AND at.active = 1 AND at.approver_type_description = ?
                ORDER BY a.id ASC LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $approverTypeDescription);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['employee_id'] ?? 0);
    }
}

if (!function_exists('itm_approval_inbox_sql_nullable_int')) {
    function itm_approval_inbox_sql_nullable_int($value)
    {
        $intVal = (int)$value;
        return $intVal > 0 ? (string)$intVal : 'NULL';
    }
}

if (!function_exists('itm_approval_inbox_sql_nullable_datetime')) {
    function itm_approval_inbox_sql_nullable_datetime($value)
    {
        $value = trim((string)$value);
        return $value === '' ? 'NULL' : "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
    }
}

if (!function_exists('itm_approval_inbox_upsert')) {
    function itm_approval_inbox_upsert(mysqli $conn, array $payload)
    {
        $companyId = (int)($payload['company_id'] ?? 0);
        $moduleSlug = trim((string)($payload['module_slug'] ?? ''));
        $recordId = (int)($payload['record_id'] ?? 0);
        $stage = trim((string)($payload['approval_stage'] ?? ''));
        if ($companyId <= 0 || $moduleSlug === '' || $recordId <= 0 || $stage === '') {
            return false;
        }

        $title = mysqli_real_escape_string($conn, (string)($payload['title'] ?? 'Approval request'));
        $status = mysqli_real_escape_string($conn, (string)($payload['status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $status = 'pending';
        }
        $actionUrl = mysqli_real_escape_string($conn, (string)($payload['action_url'] ?? ''));
        $payloadJson = $payload['payload_json'] ?? null;
        if (is_array($payloadJson)) {
            $payloadJson = json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $payloadEsc = $payloadJson !== null && $payloadJson !== ''
            ? "'" . mysqli_real_escape_string($conn, (string)$payloadJson) . "'"
            : 'NULL';

        $sql = "INSERT INTO approval_inbox_items
            (company_id, module_slug, record_id, approval_stage, title, requester_employee_id, assignee_employee_id, status, due_at, action_url, payload_json, active, created_by, updated_by)
            VALUES (
                {$companyId},
                '" . mysqli_real_escape_string($conn, $moduleSlug) . "',
                {$recordId},
                '" . mysqli_real_escape_string($conn, $stage) . "',
                '{$title}',
                " . itm_approval_inbox_sql_nullable_int($payload['requester_employee_id'] ?? 0) . ",
                " . itm_approval_inbox_sql_nullable_int($payload['assignee_employee_id'] ?? 0) . ",
                '{$status}',
                " . itm_approval_inbox_sql_nullable_datetime($payload['due_at'] ?? '') . ",
                " . ($actionUrl !== '' ? "'{$actionUrl}'" : 'NULL') . ",
                {$payloadEsc},
                1,
                " . itm_approval_inbox_sql_nullable_int($payload['created_by'] ?? ($_SESSION['employee_id'] ?? 0)) . ",
                " . itm_approval_inbox_sql_nullable_int($payload['updated_by'] ?? ($_SESSION['employee_id'] ?? 0)) . "
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                requester_employee_id = VALUES(requester_employee_id),
                assignee_employee_id = VALUES(assignee_employee_id),
                status = VALUES(status),
                due_at = VALUES(due_at),
                action_url = VALUES(action_url),
                payload_json = VALUES(payload_json),
                active = 1,
                deleted_by = NULL,
                deleted_at = NULL,
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP";

        $errCode = 0;
        $errMessage = '';
        return itm_run_query($conn, $sql, $errCode, $errMessage);
    }
}

if (!function_exists('itm_approval_inbox_fetch_for_assignee')) {
    function itm_approval_inbox_fetch_for_assignee(mysqli $conn, $companyId, $assigneeEmployeeId, array $options = [])
    {
        $companyId = (int)$companyId;
        $assigneeEmployeeId = (int)$assigneeEmployeeId;
        if ($companyId <= 0) {
            return [];
        }

        $statusFilter = trim((string)($options['status'] ?? ''));
        $search = trim((string)($options['search'] ?? ''));
        $limit = max(1, min(500, (int)($options['limit'] ?? 50)));
        $offset = max(0, (int)($options['offset'] ?? 0));
        $mineOnly = !empty($options['mine_only']);

        $where = 'ai.company_id = ? AND ai.deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];

        if ($mineOnly && $assigneeEmployeeId > 0) {
            $where .= ' AND ai.assignee_employee_id = ?';
            $types .= 'i';
            $params[] = $assigneeEmployeeId;
        }
        if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where .= ' AND ai.status = ?';
            $types .= 's';
            $params[] = $statusFilter;
        }
        if ($search !== '') {
            $pattern = (strpos($search, '%') !== false || strpos($search, '_') !== false) ? $search : '%' . $search . '%';
            $where .= ' AND (ai.title LIKE ? OR ai.module_slug LIKE ? OR ai.approval_stage LIKE ? OR CAST(ai.record_id AS CHAR) LIKE ?)';
            $types .= 'ssss';
            array_push($params, $pattern, $pattern, $pattern, $pattern);
        }

        $sql = "SELECT ai.*,
                       TRIM(CONCAT(COALESCE(req.first_name, ''), ' ', COALESCE(req.last_name, ''))) AS requester_name,
                       TRIM(CONCAT(COALESCE(asg.first_name, ''), ' ', COALESCE(asg.last_name, ''))) AS assignee_name
                FROM approval_inbox_items ai
                LEFT JOIN employees req ON req.id = ai.requester_employee_id AND req.company_id = ai.company_id
                LEFT JOIN employees asg ON asg.id = ai.assignee_employee_id AND asg.company_id = ai.company_id
                WHERE {$where}
                ORDER BY ai.status = 'pending' DESC, ai.updated_at DESC, ai.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
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

if (!function_exists('itm_approval_inbox_count_rows')) {
    function itm_approval_inbox_count_rows(mysqli $conn, $companyId, $assigneeEmployeeId, array $options = [])
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }
        $statusFilter = trim((string)($options['status'] ?? ''));
        $search = trim((string)($options['search'] ?? ''));
        $mineOnly = !empty($options['mine_only']);
        $where = 'company_id = ? AND deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];
        if ($mineOnly && (int)$assigneeEmployeeId > 0) {
            $where .= ' AND assignee_employee_id = ?';
            $types .= 'i';
            $params[] = (int)$assigneeEmployeeId;
        }
        if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where .= ' AND status = ?';
            $types .= 's';
            $params[] = $statusFilter;
        }
        if ($search !== '') {
            $pattern = (strpos($search, '%') !== false || strpos($search, '_') !== false) ? $search : '%' . $search . '%';
            $where .= ' AND (title LIKE ? OR module_slug LIKE ? OR approval_stage LIKE ? OR CAST(record_id AS CHAR) LIKE ?)';
            $types .= 'ssss';
            array_push($params, $pattern, $pattern, $pattern, $pattern);
        }
        $sql = 'SELECT COUNT(*) AS c FROM approval_inbox_items WHERE ' . $where;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_approval_inbox_count_for_assignee')) {
    function itm_approval_inbox_count_for_assignee(mysqli $conn, $companyId, $assigneeEmployeeId, $status = 'pending')
    {
        return itm_approval_inbox_count_rows($conn, $companyId, $assigneeEmployeeId, [
            'mine_only' => true,
            'status' => $status,
        ]);
    }
}

if (!function_exists('itm_approval_inbox_get_item')) {
    function itm_approval_inbox_get_item(mysqli $conn, $companyId, $itemId)
    {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM approval_inbox_items WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $itemId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_approval_inbox_apply_source_decision')) {
    function itm_approval_inbox_apply_source_decision(mysqli $conn, $companyId, array $item, $decision)
    {
        $moduleSlug = (string)($item['module_slug'] ?? '');
        $recordId = (int)($item['record_id'] ?? 0);
        $stage = (string)($item['approval_stage'] ?? '');
        $decision = strtolower(trim((string)$decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return false;
        }

        if ($moduleSlug === 'request_password') {
            $fieldMap = ['hr' => 'hr_approval_status', 'hod' => 'hod_approval_status'];
            $dateMap = ['hr' => 'hr_signature_date', 'hod' => 'hod_signature_date'];
            if (!isset($fieldMap[$stage], $dateMap[$stage])) {
                return false;
            }
            $statusValue = $decision === 'approve' ? 'Approved' : 'Declined';
            $sql = 'UPDATE request_password SET ' . $fieldMap[$stage] . ' = ?, ' . $dateMap[$stage] . ' = CURDATE() WHERE id = ? AND company_id = ? LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'sii', $statusValue, $recordId, $companyId);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return (bool)$ok;
        }

        if ($moduleSlug === 'employee_onboarding_requests') {
            $statusFieldMap = ['hod' => 'status_hod', 'hrd' => 'status_hrd', 'ism' => 'status_ism', 'gm' => 'status_gm', 'fin' => 'status_fin'];
            $dateFieldMap = ['hod' => 'hod_approval_date', 'hrd' => 'hrd_approval_date', 'ism' => 'ism_approval_date', 'gm' => 'gm_approval_date', 'fin' => 'fin_approval_date'];
            if (!isset($statusFieldMap[$stage], $dateFieldMap[$stage])) {
                return false;
            }
            $statusValue = $decision === 'approve' ? 'Approved' : 'Declined';
            $parts = ['`' . $statusFieldMap[$stage] . "`='" . mysqli_real_escape_string($conn, $statusValue) . "'"];
            if ($decision === 'approve') {
                $parts[] = '`' . $dateFieldMap[$stage] . '`=CURDATE()';
            }
            $sql = 'UPDATE employee_onboarding_requests SET ' . implode(', ', $parts) . ' WHERE id=' . $recordId . ' AND company_id=' . (int)$companyId . ' LIMIT 1';
            $errCode = 0;
            $errMessage = '';
            return itm_run_query($conn, $sql, $errCode, $errMessage);
        }

        return false;
    }
}

if (!function_exists('itm_approval_inbox_decide')) {
    function itm_approval_inbox_decide(mysqli $conn, $companyId, $sessionEmployeeId, $itemId, $decision)
    {
        $item = itm_approval_inbox_get_item($conn, (int)$companyId, (int)$itemId);
        if (!$item) {
            return ['ok' => false, 'message' => 'Inbox item not found.'];
        }
        if ((string)($item['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'This item is no longer pending.'];
        }
        $assigneeId = (int)($item['assignee_employee_id'] ?? 0);
        $isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, (int)$sessionEmployeeId);
        if (!$isAdmin && ($assigneeId <= 0 || $assigneeId !== (int)$sessionEmployeeId)) {
            return ['ok' => false, 'message' => 'You are not the assignee for this approval.'];
        }
        if (!itm_approval_inbox_apply_source_decision($conn, (int)$companyId, $item, $decision)) {
            return ['ok' => false, 'message' => 'Failed to update source record.'];
        }
        $inboxStatus = $decision === 'approve' ? 'approved' : 'rejected';
        itm_approval_inbox_upsert($conn, [
            'company_id' => (int)$companyId,
            'module_slug' => $item['module_slug'],
            'record_id' => $item['record_id'],
            'approval_stage' => $item['approval_stage'],
            'title' => $item['title'],
            'requester_employee_id' => $item['requester_employee_id'],
            'assignee_employee_id' => $item['assignee_employee_id'],
            'status' => $inboxStatus,
            'action_url' => $item['action_url'],
            'payload_json' => $item['payload_json'],
            'updated_by' => (int)$sessionEmployeeId,
        ]);
        itm_approval_inbox_sync_module_record($conn, (int)$companyId, (string)$item['module_slug'], (int)$item['record_id']);
        return ['ok' => true, 'message' => $decision === 'approve' ? 'Approved.' : 'Rejected.'];
    }
}

if (!function_exists('itm_approval_inbox_sync_request_password')) {
    function itm_approval_inbox_sync_request_password(mysqli $conn, $companyId, $recordId)
    {
        $stmt = mysqli_prepare($conn, "SELECT rp.*, d.name AS department_name,
            TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS employee_name
            FROM request_password rp
            JOIN employees e ON e.id = rp.employee_id AND e.company_id = rp.company_id
            LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = rp.company_id
            WHERE rp.id = ? AND rp.company_id = ? LIMIT 1");
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) {
            return;
        }
        if ((int)($row['active'] ?? 1) !== 1 || !empty($row['deleted_at'])) {
            foreach (['hr', 'hod', 'ism'] as $stage) {
                itm_approval_inbox_upsert($conn, ['company_id' => $companyId, 'module_slug' => 'request_password', 'record_id' => $recordId, 'approval_stage' => $stage, 'title' => 'Password request #' . $recordId, 'status' => 'cancelled']);
            }
            return;
        }
        $title = 'Password request — ' . trim((string)($row['employee_name'] ?? 'Employee')) . ' / ' . (string)($row['application'] ?? '');
        $actionUrl = 'modules/request_password/view.php?id=' . $recordId;
        $requesterId = (int)($row['requested_by_employee_id'] ?? $row['created_by'] ?? 0);
        $departmentName = trim((string)($row['department_name'] ?? ''));
        foreach (['hr' => 'HRD Approval', 'hod' => 'HOD Approval'] as $stage => $type) {
            itm_approval_inbox_upsert($conn, [
                'company_id' => $companyId,
                'module_slug' => 'request_password',
                'record_id' => $recordId,
                'approval_stage' => $stage,
                'title' => $title,
                'requester_employee_id' => $requesterId,
                'assignee_employee_id' => itm_approval_inbox_resolve_approver_employee_id($conn, $companyId, $type, $departmentName),
                'status' => itm_approval_inbox_map_source_status($row[$stage === 'hr' ? 'hr_approval_status' : 'hod_approval_status'] ?? 'Waiting'),
                'action_url' => $actionUrl,
            ]);
        }
        $ismPending = ((string)($row['hr_approval_status'] ?? '') === 'Approved') && ((string)($row['hod_approval_status'] ?? '') === 'Approved') && empty($row['ism_signature_date']);
        if ($ismPending) {
            itm_approval_inbox_upsert($conn, [
                'company_id' => $companyId,
                'module_slug' => 'request_password',
                'record_id' => $recordId,
                'approval_stage' => 'ism',
                'title' => $title . ' (ISM)',
                'requester_employee_id' => $requesterId,
                'assignee_employee_id' => itm_approval_inbox_resolve_approver_employee_id($conn, $companyId, 'ISM Approval', $departmentName),
                'status' => 'pending',
                'action_url' => $actionUrl,
            ]);
        } elseif (!empty($row['ism_signature_date'])) {
            itm_approval_inbox_upsert($conn, [
                'company_id' => $companyId,
                'module_slug' => 'request_password',
                'record_id' => $recordId,
                'approval_stage' => 'ism',
                'title' => $title . ' (ISM)',
                'status' => 'approved',
                'action_url' => $actionUrl,
            ]);
        }
    }
}

if (!function_exists('itm_approval_inbox_sync_onboarding_record')) {
    function itm_approval_inbox_sync_onboarding_record(mysqli $conn, $companyId, $recordId)
    {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM employee_onboarding_requests WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) {
            return;
        }
        if ((int)($row['active'] ?? 1) !== 1 || !empty($row['deleted_at'])) {
            foreach (['hod', 'hrd', 'ism', 'gm', 'fin'] as $stage) {
                itm_approval_inbox_upsert($conn, ['company_id' => $companyId, 'module_slug' => 'employee_onboarding_requests', 'record_id' => $recordId, 'approval_stage' => $stage, 'title' => 'Onboarding #' . $recordId, 'status' => 'cancelled']);
            }
            return;
        }
        $employeeName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = 'Onboarding #' . $recordId;
        }
        $title = 'Onboarding — ' . $employeeName;
        $actionUrl = 'modules/employee_onboarding_requests/view.php?id=' . $recordId;
        $departmentName = trim((string)($row['department_name'] ?? ''));
        $stages = [
            'hod' => ['field' => 'status_hod', 'type' => 'HOD Approval'],
            'hrd' => ['field' => 'status_hrd', 'type' => 'HRD Approval'],
            'ism' => ['field' => 'status_ism', 'type' => 'ISM Approval'],
            'gm' => ['field' => 'status_gm', 'type' => 'GM Approval'],
            'fin' => ['field' => 'status_fin', 'type' => 'FIN Approval'],
        ];
        foreach ($stages as $stage => $meta) {
            itm_approval_inbox_upsert($conn, [
                'company_id' => $companyId,
                'module_slug' => 'employee_onboarding_requests',
                'record_id' => $recordId,
                'approval_stage' => $stage,
                'title' => $title . ' (' . strtoupper($stage) . ')',
                'requester_employee_id' => (int)($row['created_by'] ?? 0),
                'assignee_employee_id' => itm_approval_inbox_resolve_approver_employee_id($conn, $companyId, $meta['type'], $stage === 'hod' ? $departmentName : ''),
                'status' => itm_approval_inbox_map_source_status($row[$meta['field']] ?? 'Waiting'),
                'action_url' => $actionUrl,
            ]);
        }
    }
}

if (!function_exists('itm_approval_inbox_sync_module_record')) {
    function itm_approval_inbox_sync_module_record(mysqli $conn, $companyId, $moduleSlug, $recordId)
    {
        if ($moduleSlug === 'request_password') {
            itm_approval_inbox_sync_request_password($conn, $companyId, $recordId);
        } elseif ($moduleSlug === 'employee_onboarding_requests') {
            itm_approval_inbox_sync_onboarding_record($conn, $companyId, $recordId);
        }
    }
}
