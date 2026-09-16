<?php
/**
 * Equipment view — related module summaries (tickets linked by equipment_id).
 */

if (!function_exists('itm_equipment_tickets_module_access_allowed')) {
    function itm_equipment_tickets_module_access_allowed($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return false;
        }

        return !function_exists('has_module_access') || has_module_access($conn, $companyId, 'tickets');
    }
}

if (!function_exists('itm_equipment_render_lookup_badge')) {
    function itm_equipment_render_lookup_badge($label, $color, $fallbackLabel = '—')
    {
        $name = trim((string)$label);
        if ($name === '') {
            $name = $fallbackLabel;
        }

        $hex = trim((string)$color);
        if ($hex === '' || !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
            $hex = '#9aa4b2';
        }

        return '<span class="badge" style="background-color:' . sanitize($hex) . '33;color:' . sanitize($hex) . ';">'
            . sanitize($name) . '</span>';
    }
}

if (!function_exists('itm_equipment_fetch_linked_tickets')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_equipment_fetch_linked_tickets($conn, $companyId, $equipmentId, $limit = 15)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $limit = max(1, min(50, (int)$limit));
        if (!($conn instanceof mysqli) || $companyId <= 0 || $equipmentId <= 0) {
            return [];
        }

        $sql = 'SELECT t.id,
                       t.title,
                       t.ticket_external_code,
                       t.due_date,
                       t.is_archived,
                       t.updated_at,
                       ts.name AS status_name,
                       ts.color AS status_color,
                       ts.is_closed AS status_is_closed,
                       tp.name AS priority_name,
                       tp.color AS priority_color,
                       COALESCE(
                           NULLIF(TRIM(CONCAT(COALESCE(a.first_name, \'\'), \' \', COALESCE(a.last_name, \'\'))), \'\'),
                           NULLIF(TRIM(COALESCE(a.username, \'\')), \'\'),
                           \'\'
                       ) AS assigned_label
                FROM tickets t
                LEFT JOIN ticket_statuses ts
                    ON ts.id = t.status_id AND ts.company_id = t.company_id
                LEFT JOIN ticket_priorities tp
                    ON tp.id = t.priority_id AND tp.company_id = t.company_id
                LEFT JOIN employees a
                    ON a.id = t.assigned_to_employee_id AND a.company_id = t.company_id
                WHERE t.company_id = ? AND t.equipment_id = ? AND t.deleted_at IS NULL
                ORDER BY t.is_archived ASC,
                         (ts.is_closed = 0 OR ts.is_closed IS NULL) DESC,
                         t.updated_at DESC,
                         t.id DESC
                LIMIT ?';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $equipmentId, $limit);
        mysqli_stmt_execute($stmt);
        $rows = [];
        if (function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
            $rows = itm_mysqli_stmt_fetch_all_assoc($stmt);
        } else {
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('itm_equipment_count_linked_tickets')) {
    function itm_equipment_count_linked_tickets($conn, $companyId, $equipmentId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $equipmentId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM tickets t
                WHERE t.company_id = ? AND t.equipment_id = ? AND t.deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        mysqli_stmt_execute($stmt);
        $count = 0;
        if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
            $row = itm_mysqli_stmt_fetch_assoc($stmt);
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        } else {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if (is_array($row)) {
                $count = (int)($row['cnt'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);

        return $count;
    }
}

if (!function_exists('itm_equipment_render_tickets_view_card')) {
    function itm_equipment_render_tickets_view_card($conn, $companyId, $equipmentId, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $equipmentId <= 0) {
            return;
        }
        if (!itm_equipment_tickets_module_access_allowed($conn, $companyId)) {
            return;
        }

        $totalTickets = itm_equipment_count_linked_tickets($conn, $companyId, $equipmentId);
        $ticketRows = itm_equipment_fetch_linked_tickets($conn, $companyId, $equipmentId, 15);
        $base = defined('BASE_URL') ? BASE_URL : '';
        $createUrl = $base . 'modules/tickets/create.php?equipment_id=' . $equipmentId;
        ?>
        <div class="card" style="margin-top:20px;">
            <h2 style="margin-top:0;">Related tickets</h2>
            <p style="margin:0 0 12px;color:#57606a;">
                Support tickets linked to this equipment<?php echo $totalTickets > 0 ? ' (' . (int)$totalTickets . ' total)' : ''; ?>.
            </p>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due date</th>
                        <th>Assigned to</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($ticketRows): ?>
                        <?php foreach ($ticketRows as $ticketRow): ?>
                            <?php
                            $ticketId = (int)($ticketRow['id'] ?? 0);
                            $ticketCode = trim((string)($ticketRow['ticket_external_code'] ?? ''));
                            $ticketTitle = trim((string)($ticketRow['title'] ?? ''));
                            $ticketLabel = $ticketCode !== '' ? $ticketCode . ' — ' . $ticketTitle : $ticketTitle;
                            if ($ticketLabel === '') {
                                $ticketLabel = 'Ticket #' . $ticketId;
                            }
                            if (!empty($ticketRow['is_archived'])) {
                                $ticketLabel .= ' (archived)';
                            }
                            $viewUrl = $base . 'modules/tickets/view.php?id=' . $ticketId;
                            ?>
                            <tr>
                                <td><?php echo sanitize($ticketLabel); ?></td>
                                <td><?php echo itm_equipment_render_lookup_badge($ticketRow['status_name'] ?? '', $ticketRow['status_color'] ?? ''); ?></td>
                                <td><?php echo itm_equipment_render_lookup_badge($ticketRow['priority_name'] ?? '', $ticketRow['priority_color'] ?? ''); ?></td>
                                <td><?php echo sanitize(itm_format_cell_scalar_display('due_date', $ticketRow['due_date'] ?? '', 'tickets')); ?></td>
                                <td><?php echo sanitize((string)($ticketRow['assigned_label'] ?? '') !== '' ? (string)$ticketRow['assigned_label'] : '—'); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="<?php echo sanitize($viewUrl); ?>" title="View ticket">🔎</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No tickets linked to this equipment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalTickets > count($ticketRows)): ?>
                <p class="form-hint" style="margin:12px 0 0;">Showing the <?php echo count($ticketRows); ?> most recent linked tickets.</p>
            <?php endif; ?>
            <p style="margin-top:16px;">
                <a class="btn btn-sm btn-primary" href="<?php echo sanitize($createUrl); ?>" title="Create ticket for this equipment">➕</a>
            </p>
        </div>
        <?php
    }
}
