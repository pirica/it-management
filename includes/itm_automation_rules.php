<?php
/**
 * Workflow automation rules — trigger dispatch, condition evaluation, and action execution.
 */

if (!function_exists('itm_automation_rules_trigger_slugs')) {
    function itm_automation_rules_trigger_slugs()
    {
        return [
            'ticket.created',
            'ticket.status_changed',
            'equipment.warranty_expiring',
        ];
    }
}

if (!function_exists('itm_automation_rules_resolve_ticket_status_name')) {
    function itm_automation_rules_resolve_ticket_status_name($conn, $companyId, $statusId)
    {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $companyId = (int)$companyId;
        $statusId = (int)$statusId;
        if ($companyId <= 0 || $statusId <= 0) {
            return '';
        }
        $stmt = mysqli_prepare($conn, 'SELECT name FROM ticket_statuses WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $statusId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return trim((string)($row['name'] ?? ''));
    }
}

if (!function_exists('itm_automation_rules_build_ticket_context')) {
    function itm_automation_rules_build_ticket_context($conn, $companyId, $ticketId, array $extra = [])
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $context = array_merge([
            'ticket_id' => $ticketId,
            'company_id' => $companyId,
            'automation_depth' => 0,
        ], $extra);

        if (!$conn instanceof mysqli || $companyId <= 0 || $ticketId <= 0) {
            return $context;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, ticket_external_code, title, description, status_id, priority_id, assigned_to_employee_id, category_id
             FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return $context;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return $context;
        }

        $context['ticket_external_code'] = (string)($row['ticket_external_code'] ?? '');
        $context['title'] = (string)($row['title'] ?? '');
        $context['description'] = (string)($row['description'] ?? '');
        $context['status_id'] = (int)($row['status_id'] ?? 0);
        $context['priority_id'] = (int)($row['priority_id'] ?? 0);
        $context['assigned_to_employee_id'] = (int)($row['assigned_to_employee_id'] ?? 0);
        $context['category_id'] = (int)($row['category_id'] ?? 0);
        $context['status_name'] = itm_automation_rules_resolve_ticket_status_name($conn, $companyId, (int)$context['status_id']);

        return $context;
    }
}

if (!function_exists('itm_automation_rules_decode_json_array')) {
    function itm_automation_rules_decode_json_array($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('itm_automation_rules_context_field_value')) {
    function itm_automation_rules_context_field_value(array $context, $field)
    {
        $field = trim((string)$field);
        if ($field === '') {
            return null;
        }
        if (array_key_exists($field, $context)) {
            return $context[$field];
        }
        if ($field === 'status_name' && isset($context['status_id'])) {
            return $context['status_name'] ?? null;
        }
        return null;
    }
}

if (!function_exists('itm_automation_rules_conditions_match')) {
    function itm_automation_rules_conditions_match(array $conditions, array $context)
    {
        if (empty($conditions)) {
            return true;
        }
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $field = trim((string)($condition['field'] ?? ''));
            $op = strtolower(trim((string)($condition['op'] ?? 'equals')));
            $expected = $condition['value'] ?? '';
            if ($field === '') {
                continue;
            }
            $actual = itm_automation_rules_context_field_value($context, $field);
            $actualStr = is_scalar($actual) ? trim((string)$actual) : '';
            $expectedStr = is_scalar($expected) ? trim((string)$expected) : '';
            if ($op === 'equals' && strcasecmp($actualStr, $expectedStr) !== 0) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('itm_automation_rules_log_run')) {
    function itm_automation_rules_log_run($conn, $companyId, $ruleId, $status, $message, array $context)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)$companyId;
        $ruleId = (int)$ruleId;
        if ($companyId <= 0 || $ruleId <= 0) {
            return false;
        }
        $status = trim((string)$status);
        if (!in_array($status, ['pending', 'success', 'failed', 'skipped'], true)) {
            $status = 'failed';
        }
        $messageEsc = mysqli_real_escape_string($conn, (string)$message);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($contextJson === false) {
            $contextJson = '{}';
        }
        $contextEsc = mysqli_real_escape_string($conn, $contextJson);
        $sql = "INSERT INTO automation_rule_runs (company_id, rule_id, status, message, context_json, ran_at, active)
                VALUES ({$companyId}, {$ruleId}, '{$status}', '{$messageEsc}', '{$contextEsc}', NOW(), 1)";
        return (bool)itm_run_query($conn, $sql);
    }
}

if (!function_exists('itm_automation_rules_stamp_last_run')) {
    function itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $ruleId = (int)$ruleId;
        $companyId = (int)$companyId;
        if ($ruleId <= 0 || $companyId <= 0) {
            return false;
        }
        $sql = 'UPDATE automation_rules SET last_run_at = NOW() WHERE id = ' . $ruleId . ' AND company_id = ' . $companyId;
        return (bool)itm_run_query($conn, $sql);
    }
}

if (!function_exists('itm_automation_rules_execute_actions')) {
    function itm_automation_rules_execute_actions($conn, $companyId, array $actions, array $context)
    {
        $companyId = (int)$companyId;
        $messages = [];
        $ok = true;

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $type = strtolower(trim((string)($action['type'] ?? '')));
            if ($type === 'notify_employee') {
                $employeeId = (int)($action['employee_id'] ?? 0);
                $title = trim((string)($action['title'] ?? 'Automation notification'));
                $body = isset($action['body']) ? (string)$action['body'] : '';
                $actionUrl = isset($action['action_url']) ? (string)$action['action_url'] : null;
                if ($employeeId <= 0) {
                    $ok = false;
                    $messages[] = 'notify_employee missing employee_id';
                    continue;
                }
                $payload = [
                    'company_id' => $companyId,
                    'module_slug' => 'automation_rules',
                    'record_id' => (int)($context['ticket_id'] ?? 0),
                    'title' => $title,
                    'body' => $body,
                ];
                if ($actionUrl !== null && $actionUrl !== '') {
                    $payload['action_url'] = $actionUrl;
                } elseif (!empty($context['ticket_id'])) {
                    $payload['action_url'] = 'modules/tickets/view.php?id=' . (int)$context['ticket_id'];
                }
                if (!itm_notify_employee($conn, $employeeId, $payload)) {
                    $ok = false;
                    $messages[] = 'notify_employee failed for employee ' . $employeeId;
                } else {
                    $messages[] = 'notify_employee sent to ' . $employeeId;
                }
                continue;
            }

            if ($type === 'send_email') {
                $toEmail = trim((string)($action['to_email'] ?? ''));
                $subject = trim((string)($action['subject'] ?? 'Automation alert'));
                $body = (string)($action['body'] ?? '');
                if ($toEmail === '') {
                    $ok = false;
                    $messages[] = 'send_email missing to_email';
                    continue;
                }
                if (!function_exists('itm_send_email')) {
                    $ok = false;
                    $messages[] = 'send_email helper unavailable';
                    continue;
                }
                $sent = itm_send_email($toEmail, $subject, $body, $companyId, ['log' => true]);
                if (!$sent) {
                    $ok = false;
                    $messages[] = 'send_email failed for ' . $toEmail;
                } else {
                    $messages[] = 'send_email sent to ' . $toEmail;
                }
                continue;
            }

            if ($type === 'set_ticket_status') {
                $ticketId = (int)($context['ticket_id'] ?? 0);
                $statusId = (int)($action['status_id'] ?? 0);
                if ($statusId <= 0 && !empty($action['status_name'])) {
                    $nameEsc = mysqli_real_escape_string($conn, trim((string)$action['status_name']));
                    $lookup = mysqli_query(
                        $conn,
                        "SELECT id FROM ticket_statuses WHERE company_id = {$companyId} AND name = '{$nameEsc}' LIMIT 1"
                    );
                    if ($lookup && ($lookupRow = mysqli_fetch_assoc($lookup))) {
                        $statusId = (int)$lookupRow['id'];
                    }
                }
                if ($ticketId <= 0 || $statusId <= 0) {
                    $ok = false;
                    $messages[] = 'set_ticket_status missing ticket or status';
                    continue;
                }
                $sql = 'UPDATE tickets SET status_id = ' . $statusId . ' WHERE id = ' . $ticketId . ' AND company_id = ' . $companyId;
                if (!itm_run_query($conn, $sql)) {
                    $ok = false;
                    $messages[] = 'set_ticket_status update failed';
                } else {
                    $messages[] = 'set_ticket_status set status_id ' . $statusId;
                    $nextContext = itm_automation_rules_build_ticket_context($conn, $companyId, $ticketId, [
                        'automation_depth' => (int)($context['automation_depth'] ?? 0) + 1,
                    ]);
                    $nextContext['previous_status_id'] = (int)($context['status_id'] ?? 0);
                    $nextContext['previous_status_name'] = (string)($context['status_name'] ?? '');
                    itm_automation_rules_dispatch($conn, $companyId, 'ticket.status_changed', $nextContext);
                }
                continue;
            }

            $ok = false;
            $messages[] = 'unknown action type ' . $type;
        }

        return [
            'ok' => $ok,
            'message' => implode('; ', $messages),
        ];
    }
}

if (!function_exists('itm_automation_rules_dispatch')) {
    function itm_automation_rules_dispatch($conn, $companyId, $triggerSlug, array $context)
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $companyId = (int)$companyId;
        $triggerSlug = trim((string)$triggerSlug);
        $depth = (int)($context['automation_depth'] ?? 0);
        if ($companyId <= 0 || $triggerSlug === '' || $depth > 2) {
            return;
        }

        $triggerEsc = mysqli_real_escape_string($conn, $triggerSlug);
        $sql = "SELECT id, name, conditions_json, actions_json
                FROM automation_rules
                WHERE company_id = {$companyId}
                  AND trigger_slug = '{$triggerEsc}'
                  AND enabled = 1
                  AND deleted_at IS NULL
                ORDER BY id ASC
                LIMIT 20";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return;
        }

        while ($rule = mysqli_fetch_assoc($res)) {
            $ruleId = (int)($rule['id'] ?? 0);
            if ($ruleId <= 0) {
                continue;
            }
            $conditions = itm_automation_rules_decode_json_array($rule['conditions_json'] ?? '');
            $actions = itm_automation_rules_decode_json_array($rule['actions_json'] ?? '');

            if (!itm_automation_rules_conditions_match($conditions, $context)) {
                itm_automation_rules_log_run($conn, $companyId, $ruleId, 'skipped', 'Conditions did not match', $context);
                itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);
                continue;
            }

            if (empty($actions)) {
                itm_automation_rules_log_run($conn, $companyId, $ruleId, 'skipped', 'No actions configured', $context);
                itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);
                continue;
            }

            $result = itm_automation_rules_execute_actions($conn, $companyId, $actions, $context);
            $status = !empty($result['ok']) ? 'success' : 'failed';
            itm_automation_rules_log_run($conn, $companyId, $ruleId, $status, (string)($result['message'] ?? ''), $context);
            itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);
        }
    }
}

if (!function_exists('itm_automation_rules_run_scheduled')) {
    function itm_automation_rules_run_scheduled($conn)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $dispatched = 0;
        $companyRes = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1');
        if (!$companyRes) {
            return 0;
        }
        $today = date('Y-m-d');
        $windowEnd = date('Y-m-d', strtotime('+30 days'));
        while ($companyRow = mysqli_fetch_assoc($companyRes)) {
            $companyId = (int)($companyRow['id'] ?? 0);
            if ($companyId <= 0) {
                continue;
            }
            $equipSql = "SELECT id, hostname, warranty_expiry, assigned_to_employee_id
                         FROM equipment
                         WHERE company_id = {$companyId}
                           AND deleted_at IS NULL
                           AND warranty_expiry IS NOT NULL
                           AND warranty_expiry >= '{$today}'
                           AND warranty_expiry <= '{$windowEnd}'";
            $equipRes = mysqli_query($conn, $equipSql);
            if (!$equipRes) {
                continue;
            }
            while ($equip = mysqli_fetch_assoc($equipRes)) {
                $context = [
                    'equipment_id' => (int)($equip['id'] ?? 0),
                    'hostname' => (string)($equip['hostname'] ?? ''),
                    'warranty_expiry' => (string)($equip['warranty_expiry'] ?? ''),
                    'assigned_to_employee_id' => (int)($equip['assigned_to_employee_id'] ?? 0),
                    'company_id' => $companyId,
                    'automation_depth' => 0,
                ];
                itm_automation_rules_dispatch($conn, $companyId, 'equipment.warranty_expiring', $context);
                $dispatched++;
            }
        }
        return $dispatched;
    }
}
