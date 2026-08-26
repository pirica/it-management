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
            'ticket.priority_changed',
            'ticket.survey_completed',
            'alert.created',
            'expense.created',
            'equipment.warranty_expiring',
            'equipment.certificate_expiring',
            'equipment.disposed',
            'problem.created',
            'problem.status_changed',
            'known_error.published',
        ];
    }
}

if (!function_exists('itm_automation_rules_resolve_ticket_priority_name')) {
    function itm_automation_rules_resolve_ticket_priority_name($conn, $companyId, $priorityId)
    {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $companyId = (int)$companyId;
        $priorityId = (int)$priorityId;
        if ($companyId <= 0 || $priorityId <= 0) {
            return '';
        }
        $stmt = mysqli_prepare($conn, 'SELECT name FROM ticket_priorities WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $priorityId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return trim((string)($row['name'] ?? ''));
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
        $context['priority_name'] = itm_automation_rules_resolve_ticket_priority_name($conn, $companyId, (int)$context['priority_id']);

        return $context;
    }
}

if (!function_exists('itm_automation_rules_build_equipment_context')) {
    function itm_automation_rules_build_equipment_context($conn, $companyId, $equipmentId, array $extra = [])
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $context = array_merge([
            'equipment_id' => $equipmentId,
            'company_id' => $companyId,
            'automation_depth' => 0,
        ], $extra);

        if (!$conn instanceof mysqli || $companyId <= 0 || $equipmentId <= 0) {
            return $context;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, hostname, name, lifecycle_stage, disposal_date, disposal_reason, warranty_expiry, certificate_expiry
             FROM equipment WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return $context;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return $context;
        }

        $context['hostname'] = (string)($row['hostname'] ?? '');
        $context['name'] = (string)($row['name'] ?? '');
        $context['lifecycle_stage'] = (string)($row['lifecycle_stage'] ?? '');
        $context['disposal_date'] = (string)($row['disposal_date'] ?? '');
        $context['disposal_reason'] = (string)($row['disposal_reason'] ?? '');
        $context['warranty_expiry'] = (string)($row['warranty_expiry'] ?? '');
        $context['certificate_expiry'] = (string)($row['certificate_expiry'] ?? '');

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
            if ($op === 'not_equals' && strcasecmp($actualStr, $expectedStr) === 0) {
                return false;
            }
            if ($op === 'contains' && stripos($actualStr, $expectedStr) === false) {
                return false;
            }
            if ($op === 'not_empty' && $actualStr === '') {
                return false;
            }
            if ($op === 'empty' && $actualStr !== '') {
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

if (!function_exists('itm_automation_rules_create_ticket')) {
    /**
     * Create a ticket from automation actions (prepared INSERT + hooks).
     *
     * @return array{ok:bool,ticket_id?:int,message?:string}
     */
    function itm_automation_rules_create_ticket($conn, $companyId, array $action, array $context)
    {
        $companyId = (int)$companyId;
        if (!$conn instanceof mysqli || $companyId <= 0) {
            return ['ok' => false, 'message' => 'invalid company'];
        }

        $title = trim((string)($action['title'] ?? $context['title'] ?? ''));
        if ($title === '') {
            $title = 'Automation ticket';
        }
        $description = (string)($action['description'] ?? $context['description'] ?? '');
        $createdBy = (int)($action['created_by_employee_id'] ?? $context['created_by_employee_id'] ?? ($_SESSION['employee_id'] ?? 0));
        if ($createdBy <= 0) {
            $empRes = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = ' . $companyId . ' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
            $empRow = $empRes ? mysqli_fetch_assoc($empRes) : null;
            $createdBy = (int)($empRow['id'] ?? 0);
        }
        if ($createdBy <= 0) {
            return ['ok' => false, 'message' => 'create_ticket missing created_by employee'];
        }

        if (!function_exists('itm_ticket_resolve_default_open_status_id')) {
            require_once ROOT_PATH . 'includes/itm_live_chat_ticket.php';
        }
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
        if ($statusId <= 0) {
            $resolvedStatus = itm_ticket_resolve_default_open_status_id($conn, $companyId);
            $statusId = $resolvedStatus !== null ? (int)$resolvedStatus : 0;
        }

        $priorityId = (int)($action['priority_id'] ?? 0);
        if ($priorityId <= 0 && !empty($action['priority_name'])) {
            $nameEsc = mysqli_real_escape_string($conn, trim((string)$action['priority_name']));
            $lookup = mysqli_query(
                $conn,
                "SELECT id FROM ticket_priorities WHERE company_id = {$companyId} AND name = '{$nameEsc}' LIMIT 1"
            );
            if ($lookup && ($lookupRow = mysqli_fetch_assoc($lookup))) {
                $priorityId = (int)$lookupRow['id'];
            }
        }
        if ($priorityId <= 0) {
            $priRes = mysqli_query($conn, 'SELECT id FROM ticket_priorities WHERE company_id = ' . $companyId . ' AND active = 1 ORDER BY level ASC LIMIT 1');
            $priRow = $priRes ? mysqli_fetch_assoc($priRes) : null;
            $priorityId = (int)($priRow['id'] ?? 0);
        }

        $assignedTo = (int)($action['assigned_to_employee_id'] ?? $context['assigned_to_employee_id'] ?? 0);
        $assignedParam = $assignedTo > 0 ? $assignedTo : null;
        $statusParam = $statusId > 0 ? $statusId : null;
        $priorityParam = $priorityId > 0 ? $priorityId : null;

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO tickets (company_id, title, description, status_id, priority_id, created_by_employee_id, assigned_to_employee_id, active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'create_ticket prepare failed'];
        }
        mysqli_stmt_bind_param(
            $stmt,
            'issiiiii',
            $companyId,
            $title,
            $description,
            $statusParam,
            $priorityParam,
            $createdBy,
            $assignedParam,
            $createdBy
        );
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'message' => 'create_ticket insert failed'];
        }
        $ticketId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if ($ticketId <= 0) {
            return ['ok' => false, 'message' => 'create_ticket missing id'];
        }

        $externalCode = 'TCK-' . str_pad((string)$ticketId, 4, '0', STR_PAD_LEFT);
        $codeStmt = mysqli_prepare($conn, 'UPDATE tickets SET ticket_external_code = ? WHERE id = ? AND company_id = ? LIMIT 1');
        if ($codeStmt) {
            mysqli_stmt_bind_param($codeStmt, 'sii', $externalCode, $ticketId, $companyId);
            mysqli_stmt_execute($codeStmt);
            mysqli_stmt_close($codeStmt);
        }

        if (function_exists('itm_ticket_sla_apply_on_create') && $priorityId > 0) {
            itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId);
        }
        if (function_exists('itm_search_index_after_module_save')) {
            require_once ROOT_PATH . 'includes/itm_search_index.php';
            itm_search_index_after_module_save($conn, 'tickets', $companyId, $ticketId);
        }
        if (function_exists('itm_webhook_queue_emit_ticket_created')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
            itm_webhook_queue_emit_ticket_created($conn, $companyId, [
                'id' => $ticketId,
                'ticket_external_code' => $externalCode,
                'title' => $title,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $depth = (int)($context['automation_depth'] ?? 0);
        $ticketContext = itm_automation_rules_build_ticket_context($conn, $companyId, $ticketId, [
            'automation_depth' => $depth + 1,
        ]);
        itm_automation_rules_dispatch($conn, $companyId, 'ticket.created', $ticketContext);

        return ['ok' => true, 'ticket_id' => $ticketId];
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

            if ($type === 'assign_ticket') {
                $ticketId = (int)($context['ticket_id'] ?? 0);
                $employeeId = (int)($action['employee_id'] ?? 0);
                if ($ticketId <= 0 || $employeeId <= 0) {
                    $ok = false;
                    $messages[] = 'assign_ticket missing ticket or employee';
                    continue;
                }
                $sql = 'UPDATE tickets SET assigned_to_employee_id = ' . $employeeId . ' WHERE id = ' . $ticketId . ' AND company_id = ' . $companyId;
                if (!itm_run_query($conn, $sql)) {
                    $ok = false;
                    $messages[] = 'assign_ticket update failed';
                } else {
                    $messages[] = 'assign_ticket set employee ' . $employeeId;
                }
                continue;
            }

            if ($type === 'set_ticket_priority') {
                $ticketId = (int)($context['ticket_id'] ?? 0);
                $priorityId = (int)($action['priority_id'] ?? 0);
                if ($priorityId <= 0 && !empty($action['priority_name'])) {
                    $nameEsc = mysqli_real_escape_string($conn, trim((string)$action['priority_name']));
                    $lookup = mysqli_query(
                        $conn,
                        "SELECT id FROM ticket_priorities WHERE company_id = {$companyId} AND name = '{$nameEsc}' LIMIT 1"
                    );
                    if ($lookup && ($lookupRow = mysqli_fetch_assoc($lookup))) {
                        $priorityId = (int)$lookupRow['id'];
                    }
                }
                if ($ticketId <= 0 || $priorityId <= 0) {
                    $ok = false;
                    $messages[] = 'set_ticket_priority missing ticket or priority';
                    continue;
                }
                $sql = 'UPDATE tickets SET priority_id = ' . $priorityId . ' WHERE id = ' . $ticketId . ' AND company_id = ' . $companyId;
                if (!itm_run_query($conn, $sql)) {
                    $ok = false;
                    $messages[] = 'set_ticket_priority update failed';
                } else {
                    $messages[] = 'set_ticket_priority set priority_id ' . $priorityId;
                }
                continue;
            }

            if ($type === 'emit_webhook') {
                $eventType = trim((string)($action['event_type'] ?? ''));
                if ($eventType === '') {
                    $ok = false;
                    $messages[] = 'emit_webhook missing event_type';
                    continue;
                }
                if (!function_exists('itm_webhook_queue_enqueue')) {
                    require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
                }
                $payload = is_array($action['payload'] ?? null) ? $action['payload'] : $context;
                $payload['event'] = $eventType;
                $payload['company_id'] = $companyId;
                $queued = itm_webhook_queue_enqueue($conn, $companyId, $eventType, $payload);
                if ($queued <= 0) {
                    $messages[] = 'emit_webhook queued 0 deliveries for ' . $eventType;
                } else {
                    $messages[] = 'emit_webhook queued ' . $queued . ' for ' . $eventType;
                }
                continue;
            }

            if ($type === 'create_ticket') {
                $result = itm_automation_rules_create_ticket($conn, $companyId, $action, $context);
                if (empty($result['ok'])) {
                    $ok = false;
                    $messages[] = 'create_ticket failed: ' . (string)($result['message'] ?? 'unknown');
                } else {
                    $messages[] = 'create_ticket id ' . (int)($result['ticket_id'] ?? 0);
                }
                continue;
            }

            if ($type === 'send_ticket_survey') {
                $ticketId = (int)($context['ticket_id'] ?? ($action['ticket_id'] ?? 0));
                $questionnaireId = (int)($action['questionnaire_id'] ?? 0);
                $sendEmail = !isset($action['send_email']) || (int)$action['send_email'] === 1 || $action['send_email'] === true;
                if ($ticketId <= 0) {
                    $ok = false;
                    $messages[] = 'send_ticket_survey missing ticket_id';
                    continue;
                }
                if (!function_exists('itm_ticket_survey_issue')) {
                    require_once ROOT_PATH . 'includes/itm_ticket_survey.php';
                }
                $surveyId = itm_ticket_survey_issue($conn, $companyId, $ticketId, $questionnaireId, '', $sendEmail);
                if ($surveyId <= 0) {
                    $ok = false;
                    $messages[] = 'send_ticket_survey failed for ticket ' . $ticketId;
                } else {
                    $messages[] = 'send_ticket_survey issued survey ' . $surveyId;
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

if (!function_exists('itm_automation_rules_run_rule')) {
    /**
     * Execute one automation rule by id (conditions, actions, run log).
     *
     * @return bool True when the rule row was found and processed (even if skipped).
     */
    function itm_automation_rules_run_rule($conn, $companyId, $ruleId, array $context)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)$companyId;
        $ruleId = (int)$ruleId;
        if ($companyId <= 0 || $ruleId <= 0) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, conditions_json, actions_json
             FROM automation_rules
             WHERE id = ? AND company_id = ? AND enabled = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ruleId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rule = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$rule) {
            return false;
        }

        $conditions = itm_automation_rules_decode_json_array($rule['conditions_json'] ?? '');
        $actions = itm_automation_rules_decode_json_array($rule['actions_json'] ?? '');

        if (!itm_automation_rules_conditions_match($conditions, $context)) {
            itm_automation_rules_log_run($conn, $companyId, $ruleId, 'skipped', 'Conditions did not match', $context);
            itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);
            return true;
        }

        if (empty($actions)) {
            itm_automation_rules_log_run($conn, $companyId, $ruleId, 'skipped', 'No actions configured', $context);
            itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);
            return true;
        }

        $result = itm_automation_rules_execute_actions($conn, $companyId, $actions, $context);
        $status = !empty($result['ok']) ? 'success' : 'failed';
        itm_automation_rules_log_run($conn, $companyId, $ruleId, $status, (string)($result['message'] ?? ''), $context);
        itm_automation_rules_stamp_last_run($conn, $ruleId, $companyId);

        return true;
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
            itm_automation_rules_run_rule($conn, $companyId, $ruleId, $context);
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
            $certSql = "SELECT id, hostname, certificate_expiry, assigned_to_employee_id
                         FROM equipment
                         WHERE company_id = {$companyId}
                           AND deleted_at IS NULL
                           AND certificate_expiry IS NOT NULL
                           AND certificate_expiry >= '{$today}'
                           AND certificate_expiry <= '{$windowEnd}'";
            $certRes = mysqli_query($conn, $certSql);
            if ($certRes) {
                while ($equip = mysqli_fetch_assoc($certRes)) {
                    $context = [
                        'equipment_id' => (int)($equip['id'] ?? 0),
                        'hostname' => (string)($equip['hostname'] ?? ''),
                        'certificate_expiry' => (string)($equip['certificate_expiry'] ?? ''),
                        'assigned_to_employee_id' => (int)($equip['assigned_to_employee_id'] ?? 0),
                        'company_id' => $companyId,
                        'automation_depth' => 0,
                    ];
                    itm_automation_rules_dispatch($conn, $companyId, 'equipment.certificate_expiring', $context);
                    $dispatched++;
                }
            }
        }
        return $dispatched;
    }
}
