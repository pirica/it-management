<?php
/**
 * Ticket SLA policy lookup, breach stamps, dashboard filters, and badges.
 */

if (!function_exists('itm_ticket_sla_policy_for_priority')) {
    function itm_ticket_sla_policy_for_priority($conn, $companyId, $priorityId)
    {
        $companyId = (int)$companyId;
        $priorityId = (int)$priorityId;
        if ($companyId <= 0 || $priorityId <= 0) {
            return null;
        }
        $sql = 'SELECT response_minutes, resolve_minutes FROM ticket_sla_policies
                WHERE company_id = ? AND priority_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $priorityId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_ticket_sla_apply_on_create')) {
    function itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId, $createdAt = null)
    {
        $policy = itm_ticket_sla_policy_for_priority($conn, $companyId, $priorityId);
        if (!$policy) {
            return false;
        }
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $base = $createdAt ? strtotime((string)$createdAt) : time();
        if ($base === false) {
            $base = time();
        }
        $responseDue = date('Y-m-d H:i:s', $base + ((int)$policy['response_minutes'] * 60));
        $resolveDue = date('Y-m-d H:i:s', $base + ((int)$policy['resolve_minutes'] * 60));
        $sql = 'UPDATE tickets SET sla_response_due_at = ?, sla_resolve_due_at = ?
                WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ssii', $responseDue, $resolveDue, $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_stamp_first_response')) {
    function itm_ticket_sla_stamp_first_response($conn, $ticketId, $companyId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'UPDATE tickets SET first_response_at = NOW()
                WHERE id = ? AND company_id = ? AND first_response_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_stamp_resolved')) {
    function itm_ticket_sla_stamp_resolved($conn, $ticketId, $companyId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'UPDATE tickets SET resolved_at = NOW()
                WHERE id = ? AND company_id = ? AND resolved_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_sla_base_scope_sql')) {
    /**
     * Shared tenant scope for SLA dashboard queries (open tickets with SLA due dates).
     */
    function itm_ticket_sla_base_scope_sql()
    {
        return 't.company_id = ? AND t.deleted_at IS NULL AND t.is_archived = 0
            AND (t.sla_response_due_at IS NOT NULL OR t.sla_resolve_due_at IS NOT NULL)';
    }
}

if (!function_exists('itm_ticket_sla_breached_predicate_sql')) {
    function itm_ticket_sla_breached_predicate_sql($alias = 't')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($a === '') {
            $a = 't';
        }
        return '(' . $a . '.sla_response_breached_at IS NOT NULL
            OR ' . $a . '.sla_resolve_breached_at IS NOT NULL
            OR (' . $a . '.first_response_at IS NULL AND ' . $a . '.sla_response_due_at IS NOT NULL AND ' . $a . '.sla_response_due_at < NOW())
            OR (' . $a . '.resolved_at IS NULL AND ' . $a . '.sla_resolve_due_at IS NOT NULL AND ' . $a . '.sla_resolve_due_at < NOW()))';
    }
}

if (!function_exists('itm_ticket_sla_at_risk_predicate_sql')) {
    function itm_ticket_sla_at_risk_predicate_sql($alias = 't')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($a === '') {
            $a = 't';
        }
        $breached = itm_ticket_sla_breached_predicate_sql($a);
        return '(NOT ' . $breached . ' AND (
            (' . $a . '.first_response_at IS NULL AND ' . $a . '.sla_response_due_at IS NOT NULL
                AND ' . $a . '.sla_response_due_at >= NOW()
                AND ' . $a . '.sla_response_due_at <= DATE_ADD(NOW(), INTERVAL 2 HOUR))
            OR (' . $a . '.resolved_at IS NULL AND ' . $a . '.sla_resolve_due_at IS NOT NULL
                AND ' . $a . '.sla_resolve_due_at >= NOW()
                AND ' . $a . '.sla_resolve_due_at <= DATE_ADD(NOW(), INTERVAL 2 HOUR))
        ))';
    }
}

if (!function_exists('itm_ticket_sla_met_predicate_sql')) {
    function itm_ticket_sla_met_predicate_sql($alias = 't')
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($a === '') {
            $a = 't';
        }
        $breached = itm_ticket_sla_breached_predicate_sql($a);
        $atRisk = itm_ticket_sla_at_risk_predicate_sql($a);
        return '(NOT ' . $breached . ' AND NOT ' . $atRisk . ' AND (
            (' . $a . '.sla_response_due_at IS NULL OR (' . $a . '.first_response_at IS NOT NULL AND ' . $a . '.first_response_at <= ' . $a . '.sla_response_due_at))
            AND (' . $a . '.sla_resolve_due_at IS NULL OR (' . $a . '.resolved_at IS NOT NULL AND ' . $a . '.resolved_at <= ' . $a . '.sla_resolve_due_at))
        ))';
    }
}

if (!function_exists('itm_ticket_sla_resolve_state')) {
    /**
     * @return string none|breached|at_risk|met|on_track
     */
    function itm_ticket_sla_resolve_state(array $row)
    {
        $hasSla = !empty($row['sla_response_due_at']) || !empty($row['sla_resolve_due_at']);
        if (!$hasSla) {
            return 'none';
        }

        $now = time();
        $responseBreached = !empty($row['sla_response_breached_at'])
            || (empty($row['first_response_at']) && !empty($row['sla_response_due_at']) && strtotime((string)$row['sla_response_due_at']) < $now);
        $resolveBreached = !empty($row['sla_resolve_breached_at'])
            || (empty($row['resolved_at']) && !empty($row['sla_resolve_due_at']) && strtotime((string)$row['sla_resolve_due_at']) < $now);

        if ($responseBreached || $resolveBreached) {
            return 'breached';
        }

        $responseAtRisk = empty($row['first_response_at']) && !empty($row['sla_response_due_at'])
            && strtotime((string)$row['sla_response_due_at']) >= $now
            && strtotime((string)$row['sla_response_due_at']) <= ($now + 7200);
        $resolveAtRisk = empty($row['resolved_at']) && !empty($row['sla_resolve_due_at'])
            && strtotime((string)$row['sla_resolve_due_at']) >= $now
            && strtotime((string)$row['sla_resolve_due_at']) <= ($now + 7200);

        if ($responseAtRisk || $resolveAtRisk) {
            return 'at_risk';
        }

        $responseMet = empty($row['sla_response_due_at'])
            || (!empty($row['first_response_at']) && strtotime((string)$row['first_response_at']) <= strtotime((string)$row['sla_response_due_at']));
        $resolveMet = empty($row['sla_resolve_due_at'])
            || (!empty($row['resolved_at']) && strtotime((string)$row['resolved_at']) <= strtotime((string)$row['sla_resolve_due_at']));

        if ($responseMet && $resolveMet) {
            return 'met';
        }

        return 'on_track';
    }
}

if (!function_exists('itm_ticket_sla_render_badge')) {
    function itm_ticket_sla_render_badge($row)
    {
        if (!is_array($row)) {
            return '<span class="badge" style="background:#f0f3f6;color:#6b7280;">—</span>';
        }

        $state = itm_ticket_sla_resolve_state($row);
        if ($state === 'none') {
            return '<span class="badge" style="background:#f0f3f6;color:#6b7280;" title="No SLA policy">—</span>';
        }

        $styles = [
            'breached' => ['bg' => '#fdecec', 'fg' => '#a52727', 'label' => 'Breached'],
            'at_risk' => ['bg' => '#fff4e5', 'fg' => '#b45309', 'label' => 'At risk'],
            'met' => ['bg' => '#e8f8ee', 'fg' => '#18794e', 'label' => 'Met'],
            'on_track' => ['bg' => '#e8f8ee', 'fg' => '#18794e', 'label' => 'On track'],
        ];
        $cfg = $styles[$state] ?? $styles['on_track'];
        $title = $cfg['label'];

        $countdown = '';
        if ($state === 'at_risk' || $state === 'on_track') {
            $nextDue = null;
            if (empty($row['first_response_at']) && !empty($row['sla_response_due_at'])) {
                $nextDue = strtotime((string)$row['sla_response_due_at']);
            } elseif (empty($row['resolved_at']) && !empty($row['sla_resolve_due_at'])) {
                $nextDue = strtotime((string)$row['sla_resolve_due_at']);
            }
            if ($nextDue !== null && $nextDue > time()) {
                $mins = (int)ceil(($nextDue - time()) / 60);
                if ($mins >= 60) {
                    $countdown = (int)floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                } else {
                    $countdown = $mins . 'm';
                }
                $title .= ' — ' . $countdown . ' remaining';
            }
        }

        $visible = sanitize($cfg['label']);
        if ($countdown !== '') {
            $visible .= ' (' . sanitize($countdown) . ')';
        }

        return '<span class="badge" style="background:' . sanitize($cfg['bg']) . ';color:' . sanitize($cfg['fg']) . ';" title="' . sanitize($title) . '">' . $visible . '</span>';
    }
}

if (!function_exists('itm_ticket_sla_filter_predicate_sql')) {
    function itm_ticket_sla_filter_predicate_sql($filter)
    {
        $filter = strtolower(trim((string)$filter));
        if ($filter === 'breached') {
            return itm_ticket_sla_breached_predicate_sql('t');
        }
        if ($filter === 'at_risk') {
            return itm_ticket_sla_at_risk_predicate_sql('t');
        }
        if ($filter === 'met') {
            return itm_ticket_sla_met_predicate_sql('t');
        }
        return '1=1';
    }
}

if (!function_exists('itm_ticket_sla_count_summary')) {
    function itm_ticket_sla_count_summary($conn, $companyId)
    {
        $companyId = (int)$companyId;
        $summary = ['at_risk' => 0, 'breached' => 0, 'met' => 0, 'total' => 0];
        if ($companyId <= 0 || !($conn instanceof mysqli)) {
            return $summary;
        }

        $base = itm_ticket_sla_base_scope_sql();
        foreach (['at_risk', 'breached', 'met'] as $filter) {
            $predicate = itm_ticket_sla_filter_predicate_sql($filter);
            $sql = 'SELECT COUNT(*) AS cnt FROM tickets t WHERE ' . $base . ' AND (' . $predicate . ')';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $summary[$filter] = (int)($row['cnt'] ?? 0);
        }

        $sqlTotal = 'SELECT COUNT(*) AS cnt FROM tickets t WHERE ' . $base;
        $stmt = mysqli_prepare($conn, $sqlTotal);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $summary['total'] = (int)($row['cnt'] ?? 0);
        }

        return $summary;
    }
}

if (!function_exists('itm_ticket_sla_list_by_filter')) {
    function itm_ticket_sla_list_by_filter($conn, $companyId, $filter, $page, $perPage)
    {
        $companyId = (int)$companyId;
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $result = ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];

        if ($companyId <= 0 || !($conn instanceof mysqli)) {
            return $result;
        }

        $base = itm_ticket_sla_base_scope_sql();
        $predicate = itm_ticket_sla_filter_predicate_sql($filter);

        $countSql = 'SELECT COUNT(*) AS cnt FROM tickets t
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            WHERE ' . $base . ' AND (' . $predicate . ')';
        $countStmt = mysqli_prepare($conn, $countSql);
        if (!$countStmt) {
            return $result;
        }
        mysqli_stmt_bind_param($countStmt, 'i', $companyId);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        mysqli_stmt_close($countStmt);
        $total = (int)($countRow['cnt'] ?? 0);
        $result['total'] = $total;
        $result['total_pages'] = max(1, (int)ceil($total / $perPage));

        $listSql = 'SELECT t.*, tp.name AS priority_name, tp.color AS priority_color,
                ts.name AS status_name, ts.color AS status_color,
                e.username AS assigned_to_username
            FROM tickets t
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            LEFT JOIN employees e ON e.id = t.assigned_to_employee_id
            WHERE ' . $base . ' AND (' . $predicate . ')
            ORDER BY COALESCE(t.sla_response_due_at, t.sla_resolve_due_at) ASC, t.id ASC
            LIMIT ? OFFSET ?';
        $listStmt = mysqli_prepare($conn, $listSql);
        if (!$listStmt) {
            return $result;
        }
        mysqli_stmt_bind_param($listStmt, 'iii', $companyId, $perPage, $offset);
        mysqli_stmt_execute($listStmt);
        $listRes = mysqli_stmt_get_result($listStmt);
        $rows = [];
        if ($listRes) {
            while ($row = mysqli_fetch_assoc($listRes)) {
                $row['sla_state'] = itm_ticket_sla_resolve_state($row);
                $row['sla_badge_html'] = itm_ticket_sla_render_badge($row);
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($listStmt);
        $result['rows'] = $rows;

        return $result;
    }
}

if (!function_exists('itm_ticket_sla_notify_breach')) {
    function itm_ticket_sla_notify_breach($conn, $companyId, $ticketRow, $breachType)
    {
        if (!function_exists('itm_notify_employee')) {
            require_once ROOT_PATH . 'includes/itm_employee_notifications.php';
        }
        $assigneeId = (int)($ticketRow['assigned_to_employee_id'] ?? 0);
        if ($assigneeId <= 0) {
            return false;
        }
        $ticketId = (int)($ticketRow['id'] ?? 0);
        $title = trim((string)($ticketRow['title'] ?? ''));
        $code = trim((string)($ticketRow['ticket_external_code'] ?? ''));
        $label = $code !== '' ? $code . ($title !== '' ? ': ' . $title : '') : ($title !== '' ? $title : 'Ticket #' . $ticketId);
        $breachLabel = $breachType === 'resolve' ? 'resolution SLA breached' : 'response SLA breached';

        return itm_notify_employee($conn, $assigneeId, [
            'company_id' => (int)$companyId,
            'module_slug' => 'tickets',
            'record_id' => $ticketId,
            'title' => 'SLA breach: ' . $breachLabel,
            'body' => $label,
            'action_url' => itm_employee_notification_build_action_url('tickets', $ticketId),
        ]);
    }
}

if (!function_exists('itm_ticket_sla_process_scheduled_breaches')) {
    /**
     * Stamp breach columns, log ticket_activity, notify assignees.
     *
     * @return array{response_stamped:int,resolve_stamped:int}
     */
    function itm_ticket_sla_process_scheduled_breaches($conn, $companyId = null)
    {
        $stats = ['response_stamped' => 0, 'resolve_stamped' => 0];
        if (!($conn instanceof mysqli)) {
            return $stats;
        }

        $companyFilter = $companyId !== null ? (int)$companyId : 0;
        $companyClause = $companyFilter > 0 ? ' AND t.company_id = ?' : '';
        $types = $companyFilter > 0 ? 'i' : '';

        $responseSql = 'SELECT t.id, t.company_id, t.title, t.ticket_external_code, t.assigned_to_employee_id, t.sla_response_due_at
            FROM tickets t
            WHERE t.deleted_at IS NULL AND t.is_archived = 0
            AND t.first_response_at IS NULL
            AND t.sla_response_due_at IS NOT NULL
            AND t.sla_response_due_at < NOW()
            AND t.sla_response_breached_at IS NULL' . $companyClause;

        $responseStmt = mysqli_prepare($conn, $responseSql);
        if ($responseStmt) {
            if ($companyFilter > 0) {
                mysqli_stmt_bind_param($responseStmt, $types, $companyFilter);
            }
            mysqli_stmt_execute($responseStmt);
            $res = mysqli_stmt_get_result($responseStmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $tid = (int)$row['id'];
                    $cid = (int)$row['company_id'];
                    $upd = mysqli_prepare($conn, 'UPDATE tickets SET sla_response_breached_at = NOW() WHERE id = ? AND company_id = ? AND sla_response_breached_at IS NULL');
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, 'ii', $tid, $cid);
                        if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
                            $stats['response_stamped']++;
                            itm_ticket_activity_log($conn, $cid, $tid, null, 'sla_response_breached', [
                                'sla_response_due_at' => $row['sla_response_due_at'],
                            ]);
                            itm_ticket_sla_notify_breach($conn, $cid, $row, 'response');
                        }
                        mysqli_stmt_close($upd);
                    }
                }
            }
            mysqli_stmt_close($responseStmt);
        }

        $resolveSql = 'SELECT t.id, t.company_id, t.title, t.ticket_external_code, t.assigned_to_employee_id, t.sla_resolve_due_at
            FROM tickets t
            WHERE t.deleted_at IS NULL AND t.is_archived = 0
            AND t.resolved_at IS NULL
            AND t.sla_resolve_due_at IS NOT NULL
            AND t.sla_resolve_due_at < NOW()
            AND t.sla_resolve_breached_at IS NULL' . $companyClause;

        $resolveStmt = mysqli_prepare($conn, $resolveSql);
        if ($resolveStmt) {
            if ($companyFilter > 0) {
                mysqli_stmt_bind_param($resolveStmt, $types, $companyFilter);
            }
            mysqli_stmt_execute($resolveStmt);
            $res = mysqli_stmt_get_result($resolveStmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $tid = (int)$row['id'];
                    $cid = (int)$row['company_id'];
                    $upd = mysqli_prepare($conn, 'UPDATE tickets SET sla_resolve_breached_at = NOW() WHERE id = ? AND company_id = ? AND sla_resolve_breached_at IS NULL');
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, 'ii', $tid, $cid);
                        if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
                            $stats['resolve_stamped']++;
                            itm_ticket_activity_log($conn, $cid, $tid, null, 'sla_resolve_breached', [
                                'sla_resolve_due_at' => $row['sla_resolve_due_at'],
                            ]);
                            itm_ticket_sla_notify_breach($conn, $cid, $row, 'resolve');
                        }
                        mysqli_stmt_close($upd);
                    }
                }
            }
            mysqli_stmt_close($resolveStmt);
        }

        return $stats;
    }
}

if (!function_exists('itm_ticket_sla_check_breaches')) {
    function itm_ticket_sla_check_breaches($conn, $companyId, $ticketId, $actorEmployeeId)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $sql = 'SELECT first_response_at, resolved_at, sla_response_due_at, sla_resolve_due_at,
                sla_response_breached_at, sla_resolve_breached_at
                FROM tickets WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return;
        }
        $now = time();
        if ($row['first_response_at'] === null && empty($row['sla_response_breached_at'])
            && !empty($row['sla_response_due_at']) && strtotime($row['sla_response_due_at']) < $now) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'sla_response_breached', [
                'sla_response_due_at' => $row['sla_response_due_at'],
            ]);
        }
        if ($row['resolved_at'] === null && empty($row['sla_resolve_breached_at'])
            && !empty($row['sla_resolve_due_at']) && strtotime($row['sla_resolve_due_at']) < $now) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'sla_resolve_breached', [
                'sla_resolve_due_at' => $row['sla_resolve_due_at'],
            ]);
        }
    }
}
