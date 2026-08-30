<?php
/**
 * Change request helpers — CMDB blast-radius, CAB workflow, inbox, calendar, reminders, events.
 */

require_once __DIR__ . '/itm_cmdb.php';

if (!function_exists('itm_change_request_statuses')) {
    /**
     * @return array<string,string>
     */
    function itm_change_request_statuses(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'implemented' => 'Implemented',
            'cancelled' => 'Cancelled',
        ];
    }
}

if (!function_exists('itm_change_request_change_types')) {
    /**
     * @return array<string,string>
     */
    function itm_change_request_change_types(): array
    {
        return [
            'standard' => 'Standard',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
        ];
    }
}

if (!function_exists('itm_change_request_risk_levels')) {
    /**
     * @return array<string,string>
     */
    function itm_change_request_risk_levels(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }
}

if (!function_exists('itm_change_request_type_label')) {
    function itm_change_request_type_label(string $type): string
    {
        $map = itm_change_request_change_types();
        return $map[$type] ?? ucwords(str_replace('_', ' ', $type));
    }
}

if (!function_exists('itm_change_request_risk_label')) {
    function itm_change_request_risk_label(string $risk): string
    {
        $map = itm_change_request_risk_levels();
        return $map[$risk] ?? ucwords(str_replace('_', ' ', $risk));
    }
}

if (!function_exists('itm_change_request_status_label')) {
    function itm_change_request_status_label(string $status): string
    {
        $map = itm_change_request_statuses();
        return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('itm_change_request_cab_stage_slug')) {
    function itm_change_request_cab_stage_slug(int $approverEmployeeId): string
    {
        return 'cab_' . (int)$approverEmployeeId;
    }
}

if (!function_exists('itm_change_request_fetch_row')) {
    function itm_change_request_fetch_row(mysqli $conn, int $companyId, int $changeRequestId): ?array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM change_requests WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $changeRequestId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_change_request_settings_get')) {
    function itm_change_request_settings_get(mysqli $conn, int $companyId): array
    {
        if ($companyId <= 0) {
            return ['reminder_days_before' => 1];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT reminder_days_before FROM change_request_settings
             WHERE company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['reminder_days_before' => 1];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['reminder_days_before' => 1];
        }
        return [
            'reminder_days_before' => max(0, (int)($row['reminder_days_before'] ?? 1)),
        ];
    }
}

if (!function_exists('itm_change_request_ensure_settings_row')) {
    function itm_change_request_ensure_settings_row(mysqli $conn, int $companyId, int $employeeId = 0): void
    {
        if ($companyId <= 0) {
            return;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM change_request_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if ($exists) {
            return;
        }
        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO change_request_settings (company_id, reminder_days_before, active, created_by) VALUES (?, 1, 1, ?)'
        );
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'ii', $companyId, $employeeId);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

if (!function_exists('itm_change_request_list_cab_member_ids')) {
    /**
     * @return array<int,int>
     */
    function itm_change_request_list_cab_member_ids(mysqli $conn, int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }
        $ids = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT employee_id FROM change_request_cab_members
             WHERE company_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['employee_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        mysqli_stmt_close($stmt);
        return array_values($ids);
    }
}

if (!function_exists('itm_change_request_ensure_cab_members')) {
    function itm_change_request_ensure_cab_members(mysqli $conn, int $companyId, int $employeeId = 0): void
    {
        if ($companyId <= 0) {
            return;
        }
        $existing = itm_change_request_list_cab_member_ids($conn, $companyId);
        if ($existing !== []) {
            return;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT e.id FROM employees e
             INNER JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
             WHERE e.company_id = ? AND er.name = \'Admin\' AND e.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $adminId = (int)($row['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }
        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO change_request_cab_members (company_id, employee_id, active, created_by)
             VALUES (?, ?, 1, ?)'
        );
        if ($ins) {
            $createdBy = $employeeId > 0 ? $employeeId : $adminId;
            mysqli_stmt_bind_param($ins, 'iii', $companyId, $adminId, $createdBy);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

if (!function_exists('itm_change_request_form_status_options')) {
    /**
     * @return array<string,string>
     */
    function itm_change_request_form_status_options(mysqli $conn, int $employeeId, string $currentStatus): array
    {
        $all = itm_change_request_statuses();
        $isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId);
        if ($isAdmin) {
            return $all;
        }
        $allowed = ['draft' => $all['draft'], 'submitted' => $all['submitted'], 'cancelled' => $all['cancelled']];
        if ($currentStatus === 'approved') {
            $allowed['implemented'] = $all['implemented'];
        }
        return $allowed;
    }
}

if (!function_exists('itm_change_request_list_affected_ci_ids')) {
    /**
     * @return array<int,int>
     */
    function itm_change_request_list_affected_ci_ids(mysqli $conn, int $companyId, int $changeRequestId): array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return [];
        }
        $ids = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT configuration_item_id FROM change_request_configuration_items
             WHERE company_id = ? AND change_request_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $changeRequestId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['configuration_item_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        mysqli_stmt_close($stmt);
        return array_values($ids);
    }
}

if (!function_exists('itm_change_request_list_affected_rows')) {
    function itm_change_request_list_affected_rows(mysqli $conn, int $companyId, int $changeRequestId): array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ci.id, ci.name, cit.name AS ci_type_name, cit.icon AS ci_type_icon
             FROM change_request_configuration_items crci
             INNER JOIN configuration_items ci ON ci.id = crci.configuration_item_id AND ci.company_id = crci.company_id
             INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
             WHERE crci.company_id = ? AND crci.change_request_id = ? AND crci.deleted_at IS NULL AND crci.active = 1
               AND ci.deleted_at IS NULL
             ORDER BY ci.name'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $changeRequestId);
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

if (!function_exists('itm_change_request_list_approval_rows')) {
    function itm_change_request_list_approval_rows(mysqli $conn, int $companyId, int $changeRequestId): array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT a.*, TRIM(CONCAT(COALESCE(e.first_name, \'\'), \' \', COALESCE(e.last_name, \'\'))) AS approver_name,
                    e.username AS approver_username
             FROM change_request_approvals a
             INNER JOIN employees e ON e.id = a.approver_employee_id AND e.company_id = a.company_id
             WHERE a.company_id = ? AND a.change_request_id = ? AND a.deleted_at IS NULL
             ORDER BY a.approver_employee_id'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $changeRequestId);
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

if (!function_exists('itm_change_request_replace_affected_cis')) {
    /**
     * Soft-delete prior links and upsert the selected blast-radius CI set.
     */
    function itm_change_request_replace_affected_cis(
        mysqli $conn,
        int $companyId,
        int $changeRequestId,
        array $configurationItemIds,
        int $employeeId = 0
    ): void {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return;
        }

        $normalized = [];
        foreach ($configurationItemIds as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        $soft = mysqli_prepare(
            $conn,
            'UPDATE change_request_configuration_items
             SET active = 0, deleted_by = ?, deleted_at = NOW()
             WHERE company_id = ? AND change_request_id = ? AND deleted_at IS NULL'
        );
        if ($soft) {
            mysqli_stmt_bind_param($soft, 'iii', $employeeId, $companyId, $changeRequestId);
            mysqli_stmt_execute($soft);
            mysqli_stmt_close($soft);
        }

        foreach ($normalized as $ciId) {
            $check = mysqli_prepare(
                $conn,
                'SELECT id FROM change_request_configuration_items
                 WHERE company_id = ? AND change_request_id = ? AND configuration_item_id = ?
                 LIMIT 1'
            );
            if (!$check) {
                continue;
            }
            mysqli_stmt_bind_param($check, 'iii', $companyId, $changeRequestId, $ciId);
            mysqli_stmt_execute($check);
            $cRes = mysqli_stmt_get_result($check);
            $existing = $cRes ? mysqli_fetch_assoc($cRes) : null;
            mysqli_stmt_close($check);

            if ($existing) {
                $linkId = (int)($existing['id'] ?? 0);
                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE change_request_configuration_items
                     SET active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ?'
                );
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'iii', $employeeId, $linkId, $companyId);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
                continue;
            }

            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO change_request_configuration_items
                 (company_id, change_request_id, configuration_item_id, active, created_by)
                 VALUES (?, ?, ?, 1, ?)'
            );
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiii', $companyId, $changeRequestId, $ciId, $employeeId);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
    }
}

if (!function_exists('itm_change_request_dispatch_events')) {
    function itm_change_request_dispatch_events(mysqli $conn, int $companyId, string $eventType, array $row, array $extra = []): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $companyId = (int)$companyId;
        $context = array_merge([
            'change_request_id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'change_type' => (string)($row['change_type'] ?? ''),
            'risk_level' => (string)($row['risk_level'] ?? ''),
            'ticket_id' => (int)($row['ticket_id'] ?? 0),
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
            'change_request_id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'change_type' => (string)($row['change_type'] ?? ''),
            'risk_level' => (string)($row['risk_level'] ?? ''),
            'ticket_id' => (int)($row['ticket_id'] ?? 0),
            'occurred_at' => date('Y-m-d H:i:s'),
        ], $extra);
        itm_webhook_queue_enqueue($conn, $companyId, $eventType, $payload);
    }
}

if (!function_exists('itm_change_request_required_approval_count')) {
    function itm_change_request_required_approval_count(mysqli $conn, int $companyId, string $changeType, int $cabMemberCount): int
    {
        $cabMemberCount = max(0, $cabMemberCount);
        if ($cabMemberCount === 0) {
            return 0;
        }
        if ($changeType === 'emergency') {
            return 1;
        }
        return $cabMemberCount;
    }
}

if (!function_exists('itm_change_request_sync_cab_inbox')) {
    function itm_change_request_sync_cab_inbox(mysqli $conn, int $companyId, int $changeRequestId): void
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return;
        }
        if (!function_exists('itm_approval_inbox_upsert')) {
            require_once ROOT_PATH . 'includes/itm_approval_inbox.php';
        }
        $row = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        if (!$row) {
            return;
        }
        $status = (string)($row['status'] ?? '');
        $title = 'Change request — ' . (string)($row['title'] ?? '#' . $changeRequestId);
        $actionUrl = 'modules/change_requests/view.php?id=' . $changeRequestId;
        $requesterId = (int)($row['created_by'] ?? 0);

        if ($status === 'cancelled' || (int)($row['active'] ?? 1) !== 1) {
            foreach (itm_change_request_list_cab_member_ids($conn, $companyId) as $memberId) {
                itm_approval_inbox_upsert($conn, [
                    'company_id' => $companyId,
                    'module_slug' => 'change_requests',
                    'record_id' => $changeRequestId,
                    'approval_stage' => itm_change_request_cab_stage_slug($memberId),
                    'title' => $title,
                    'status' => 'cancelled',
                    'action_url' => $actionUrl,
                ]);
            }
            return;
        }

        $approvals = itm_change_request_list_approval_rows($conn, $companyId, $changeRequestId);
        foreach ($approvals as $approval) {
            $approverId = (int)($approval['approver_employee_id'] ?? 0);
            if ($approverId <= 0) {
                continue;
            }
            $decision = (string)($approval['decision'] ?? 'pending');
            $inboxStatus = 'pending';
            if ($decision === 'approved') {
                $inboxStatus = 'approved';
            } elseif ($decision === 'rejected') {
                $inboxStatus = 'rejected';
            } elseif ($status !== 'submitted') {
                $inboxStatus = 'cancelled';
            }
            itm_approval_inbox_upsert($conn, [
                'company_id' => $companyId,
                'module_slug' => 'change_requests',
                'record_id' => $changeRequestId,
                'approval_stage' => itm_change_request_cab_stage_slug($approverId),
                'title' => $title . ' (CAB)',
                'requester_employee_id' => $requesterId,
                'assignee_employee_id' => $approverId,
                'status' => $inboxStatus,
                'action_url' => $actionUrl,
            ]);
        }
    }
}

if (!function_exists('itm_change_request_begin_cab_review')) {
    function itm_change_request_begin_cab_review(mysqli $conn, int $companyId, int $changeRequestId, int $actorEmployeeId): void
    {
        itm_change_request_ensure_settings_row($conn, $companyId, $actorEmployeeId);
        itm_change_request_ensure_cab_members($conn, $companyId, $actorEmployeeId);
        $cabIds = itm_change_request_list_cab_member_ids($conn, $companyId);
        foreach ($cabIds as $approverId) {
            $check = mysqli_prepare(
                $conn,
                'SELECT id FROM change_request_approvals
                 WHERE company_id = ? AND change_request_id = ? AND approver_employee_id = ?
                 LIMIT 1'
            );
            if (!$check) {
                continue;
            }
            mysqli_stmt_bind_param($check, 'iii', $companyId, $changeRequestId, $approverId);
            mysqli_stmt_execute($check);
            $cRes = mysqli_stmt_get_result($check);
            $existing = $cRes ? mysqli_fetch_assoc($cRes) : null;
            mysqli_stmt_close($check);
            if ($existing) {
                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE change_request_approvals
                     SET decision = \'pending\', comment = NULL, decided_at = NULL,
                         active = 1, deleted_at = NULL, deleted_by = NULL, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ?'
                );
                if ($upd) {
                    $linkId = (int)($existing['id'] ?? 0);
                    mysqli_stmt_bind_param($upd, 'iii', $actorEmployeeId, $linkId, $companyId);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
                continue;
            }
            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO change_request_approvals
                 (company_id, change_request_id, approver_employee_id, decision, active, created_by)
                 VALUES (?, ?, ?, \'pending\', 1, ?)'
            );
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiii', $companyId, $changeRequestId, $approverId, $actorEmployeeId);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
        itm_change_request_sync_cab_inbox($conn, $companyId, $changeRequestId);
    }
}

if (!function_exists('itm_change_request_evaluate_cab_quorum')) {
    function itm_change_request_evaluate_cab_quorum(mysqli $conn, int $companyId, int $changeRequestId): ?string
    {
        $row = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        if (!$row || (string)($row['status'] ?? '') !== 'submitted') {
            return null;
        }
        $approvals = itm_change_request_list_approval_rows($conn, $companyId, $changeRequestId);
        if ($approvals === []) {
            return null;
        }
        $approved = 0;
        foreach ($approvals as $approval) {
            $decision = (string)($approval['decision'] ?? '');
            if ($decision === 'rejected') {
                return 'rejected';
            }
            if ($decision === 'approved') {
                $approved++;
            }
        }
        $required = itm_change_request_required_approval_count(
            $conn,
            $companyId,
            (string)($row['change_type'] ?? 'standard'),
            count($approvals)
        );
        if ($required > 0 && $approved >= $required) {
            return 'approved';
        }
        return null;
    }
}

if (!function_exists('itm_change_request_apply_cab_decision')) {
    function itm_change_request_apply_cab_decision(
        mysqli $conn,
        int $companyId,
        int $changeRequestId,
        int $approverEmployeeId,
        string $decision
    ): bool {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return false;
        }
        $dbDecision = $decision === 'approve' ? 'approved' : 'rejected';
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE change_request_approvals
             SET decision = ?, decided_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE company_id = ? AND change_request_id = ? AND approver_employee_id = ?
               AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'siiii', $dbDecision, $approverEmployeeId, $companyId, $changeRequestId, $approverEmployeeId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            return false;
        }

        itm_change_request_sync_cab_inbox($conn, $companyId, $changeRequestId);
        $nextStatus = itm_change_request_evaluate_cab_quorum($conn, $companyId, $changeRequestId);
        if ($nextStatus === null) {
            return true;
        }
        $oldRow = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        $upd = mysqli_prepare(
            $conn,
            'UPDATE change_requests SET status = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$upd) {
            return false;
        }
        mysqli_stmt_bind_param($upd, 'siii', $nextStatus, $approverEmployeeId, $changeRequestId, $companyId);
        if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) !== 1) {
            mysqli_stmt_close($upd);
            return false;
        }
        mysqli_stmt_close($upd);
        $newRow = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        if ($newRow && $oldRow) {
            $event = $nextStatus === 'approved' ? 'change.approved' : 'change.rejected';
            itm_change_request_dispatch_events($conn, $companyId, $event, $newRow, [
                'old_status' => (string)($oldRow['status'] ?? ''),
                'new_status' => $nextStatus,
            ]);
            itm_change_request_dispatch_events($conn, $companyId, 'change.status_changed', $newRow, [
                'old_status' => (string)($oldRow['status'] ?? ''),
                'new_status' => $nextStatus,
            ]);
        }
        itm_change_request_sync_cab_inbox($conn, $companyId, $changeRequestId);
        return true;
    }
}

if (!function_exists('itm_change_request_ensure_submitted_cab_state')) {
    function itm_change_request_ensure_submitted_cab_state(mysqli $conn, int $companyId, int $changeRequestId, int $actorEmployeeId = 0): void
    {
        $row = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        if (!$row || (string)($row['status'] ?? '') !== 'submitted') {
            return;
        }
        $approvals = itm_change_request_list_approval_rows($conn, $companyId, $changeRequestId);
        if ($approvals === []) {
            itm_change_request_begin_cab_review($conn, $companyId, $changeRequestId, $actorEmployeeId);
        } else {
            itm_change_request_sync_cab_inbox($conn, $companyId, $changeRequestId);
        }
    }
}

if (!function_exists('itm_change_request_validate_ticket_id')) {
    function itm_change_request_validate_ticket_id(mysqli $conn, int $companyId, int $ticketId): bool
    {
        if ($ticketId <= 0) {
            return true;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row !== null;
    }
}

if (!function_exists('itm_change_request_save')) {
    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,error?:string,id?:int}
     */
    function itm_change_request_save(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        int $changeRequestId,
        array $data,
        array $configurationItemIds
    ): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }
        $sourceCiId = (int)($data['source_configuration_item_id'] ?? 0);
        if ($sourceCiId <= 0) {
            return ['ok' => false, 'error' => 'Source configuration item is required.'];
        }
        $changeTypes = itm_change_request_change_types();
        $changeType = strtolower(trim((string)($data['change_type'] ?? 'standard')));
        if (!isset($changeTypes[$changeType])) {
            $changeType = 'standard';
        }
        $riskLevels = itm_change_request_risk_levels();
        $riskLevel = strtolower(trim((string)($data['risk_level'] ?? 'medium')));
        if (!isset($riskLevels[$riskLevel])) {
            $riskLevel = 'medium';
        }
        $rollbackPlan = trim((string)($data['rollback_plan'] ?? ''));
        $ticketId = (int)($data['ticket_id'] ?? 0);
        if ($ticketId < 0) {
            $ticketId = 0;
        }
        if (!itm_change_request_validate_ticket_id($conn, $companyId, $ticketId)) {
            return ['ok' => false, 'error' => 'Linked ticket was not found for this company.'];
        }
        $statuses = itm_change_request_statuses();
        $newStatus = strtolower(trim((string)($data['status'] ?? 'draft')));
        if (!isset($statuses[$newStatus])) {
            $newStatus = 'draft';
        }
        $existing = $changeRequestId > 0 ? itm_change_request_fetch_row($conn, $companyId, $changeRequestId) : null;
        $oldStatus = $existing ? (string)($existing['status'] ?? 'draft') : 'draft';
        $isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId);
        $allowed = itm_change_request_form_status_options($conn, $employeeId, $oldStatus);
        if (!$isAdmin && !isset($allowed[$newStatus])) {
            return ['ok' => false, 'error' => 'That status change requires CAB approval or administrator access.'];
        }
        if (!$isAdmin && in_array($newStatus, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Approved and rejected statuses are set by CAB review.'];
        }

        $scheduledStart = trim((string)($data['scheduled_start'] ?? ''));
        $scheduledEnd = trim((string)($data['scheduled_end'] ?? ''));
        $startDate = $scheduledStart !== '' ? itm_parse_date_input($scheduledStart) : null;
        $endDate = $scheduledEnd !== '' ? itm_parse_date_input($scheduledEnd) : null;
        if ($scheduledStart !== '' && $startDate === null) {
            return ['ok' => false, 'error' => 'Scheduled start must be dd/mmm/yyyy.'];
        }
        if ($scheduledEnd !== '' && $endDate === null) {
            return ['ok' => false, 'error' => 'Scheduled end must be dd/mmm/yyyy.'];
        }

        $description = trim((string)($data['description'] ?? ''));
        $startSql = $startDate;
        $endSql = $endDate;

        if ($changeRequestId > 0 && $existing) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE change_requests
                 SET source_configuration_item_id = ?, title = ?, description = ?, change_type = ?, risk_level = ?,
                     rollback_plan = ?, ticket_id = NULLIF(?, 0), status = ?, scheduled_start = ?, scheduled_end = ?,
                     updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not prepare update.'];
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssssisssiii',
                $sourceCiId,
                $title,
                $description,
                $changeType,
                $riskLevel,
                $rollbackPlan,
                $ticketId,
                $newStatus,
                $startSql,
                $endSql,
                $employeeId,
                $changeRequestId,
                $companyId
            );
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_error($conn);
                mysqli_stmt_close($stmt);
                return ['ok' => false, 'error' => $err !== '' ? $err : 'Update failed.'];
            }
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO change_requests
                 (company_id, source_configuration_item_id, title, description, change_type, risk_level,
                  rollback_plan, ticket_id, status, scheduled_start, scheduled_end, active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, 1, ?)'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not prepare insert.'];
            }
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssisssi',
                $companyId,
                $sourceCiId,
                $title,
                $description,
                $changeType,
                $riskLevel,
                $rollbackPlan,
                $ticketId,
                $newStatus,
                $startSql,
                $endSql,
                $employeeId
            );
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_error($conn);
                mysqli_stmt_close($stmt);
                return ['ok' => false, 'error' => $err !== '' ? $err : 'Insert failed.'];
            }
            $changeRequestId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            if ($changeRequestId <= 0) {
                return ['ok' => false, 'error' => 'Insert did not return an id.'];
            }
        }

        itm_change_request_replace_affected_cis($conn, $companyId, $changeRequestId, $configurationItemIds, $employeeId);
        $row = itm_change_request_fetch_row($conn, $companyId, $changeRequestId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Saved row could not be loaded.'];
        }

        if ($oldStatus !== 'submitted' && $newStatus === 'submitted') {
            itm_change_request_begin_cab_review($conn, $companyId, $changeRequestId, $employeeId);
            itm_change_request_dispatch_events($conn, $companyId, 'change.submitted', $row, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }
        if ($oldStatus !== $newStatus) {
            itm_change_request_dispatch_events($conn, $companyId, 'change.status_changed', $row, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            if ($newStatus === 'implemented') {
                itm_change_request_dispatch_events($conn, $companyId, 'change.implemented', $row, [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            }
            if ($newStatus === 'cancelled') {
                itm_change_request_sync_cab_inbox($conn, $companyId, $changeRequestId);
            }
        }

        if ($newStatus === 'submitted') {
            itm_change_request_ensure_submitted_cab_state($conn, $companyId, $changeRequestId, $employeeId);
        }

        return ['ok' => true, 'id' => $changeRequestId];
    }
}

if (!function_exists('itm_change_request_list_calendar_rows')) {
    function itm_change_request_list_calendar_rows(mysqli $conn, int $companyId, string $startDate, string $endDate): array
    {
        if ($companyId <= 0 || $startDate === '' || $endDate === '') {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, title, status, change_type, risk_level, scheduled_start, scheduled_end
             FROM change_requests
             WHERE company_id = ? AND deleted_at IS NULL AND active = 1
               AND scheduled_start IS NOT NULL
               AND scheduled_start BETWEEN ? AND ?
               AND status IN (\'submitted\', \'approved\', \'implemented\')'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iss', $companyId, $startDate, $endDate);
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

if (!function_exists('itm_change_request_process_reminders')) {
    /**
     * @return array{sent:int,matches:int}
     */
    function itm_change_request_process_reminders(mysqli $conn, int $companyId): array
    {
        if ($companyId <= 0) {
            return ['sent' => 0, 'matches' => 0];
        }
        itm_change_request_ensure_settings_row($conn, $companyId);
        $settings = itm_change_request_settings_get($conn, $companyId);
        $daysBefore = max(0, (int)($settings['reminder_days_before'] ?? 1));
        $targetDate = date('Y-m-d', strtotime('+' . $daysBefore . ' days'));
        $stmt = mysqli_prepare(
            $conn,
            'SELECT cr.*, TRIM(CONCAT(COALESCE(e.first_name, \'\'), \' \', COALESCE(e.last_name, \'\'))) AS requester_name,
                    e.work_email AS requester_email
             FROM change_requests cr
             LEFT JOIN employees e ON e.id = cr.created_by AND e.company_id = cr.company_id
             WHERE cr.company_id = ? AND cr.deleted_at IS NULL AND cr.active = 1
               AND cr.status IN (\'submitted\', \'approved\')
               AND cr.scheduled_start = ?
               AND cr.reminder_sent_at IS NULL'
        );
        if (!$stmt) {
            return ['sent' => 0, 'matches' => 0];
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $targetDate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        if ($rows === []) {
            return ['sent' => 0, 'matches' => 0];
        }

        if (!function_exists('itm_send_email')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }

        $sent = 0;
        foreach ($rows as $row) {
            $changeId = (int)($row['id'] ?? 0);
            $title = (string)($row['title'] ?? '');
            $viewUrl = (function_exists('BASE_URL') ? BASE_URL : '') . 'modules/change_requests/view.php?id=' . $changeId;
            $subject = 'Change reminder: ' . $title;
            $html = '<p>Scheduled IT change <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> starts on '
                . htmlspecialchars((string)($row['scheduled_start'] ?? ''), ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p><a href="' . htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') . '">Open change request</a></p>';

            $recipients = [];
            $requesterEmail = trim((string)($row['requester_email'] ?? ''));
            if ($requesterEmail !== '') {
                $recipients[] = $requesterEmail;
            }
            foreach (itm_change_request_list_cab_member_ids($conn, $companyId) as $memberId) {
                $empStmt = mysqli_prepare(
                    $conn,
                    'SELECT work_email FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
                );
                if (!$empStmt) {
                    continue;
                }
                mysqli_stmt_bind_param($empStmt, 'ii', $memberId, $companyId);
                mysqli_stmt_execute($empStmt);
                $empRes = mysqli_stmt_get_result($empStmt);
                $empRow = $empRes ? mysqli_fetch_assoc($empRes) : null;
                mysqli_stmt_close($empStmt);
                $email = trim((string)($empRow['work_email'] ?? ''));
                if ($email !== '') {
                    $recipients[] = $email;
                }
            }
            $recipients = array_values(array_unique($recipients));
            if ($recipients !== []) {
                foreach ($recipients as $to) {
                    if (itm_send_email($conn, $companyId, $to, $subject, $html)) {
                        $sent++;
                    }
                }
            }
            $mark = mysqli_prepare(
                $conn,
                'UPDATE change_requests SET reminder_sent_at = NOW() WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($mark) {
                mysqli_stmt_bind_param($mark, 'ii', $changeId, $companyId);
                mysqli_stmt_execute($mark);
                mysqli_stmt_close($mark);
            }
        }

        return ['sent' => $sent, 'matches' => count($rows)];
    }
}
