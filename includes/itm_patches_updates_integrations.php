<?php
/**
 * Patches & Updates — calendar feed, dashboard metrics, and module integration summary.
 *
 * Why: One tenant-safe contract for open due-date rows shared by calendar, dashboard widgets, and list callouts.
 */

require_once __DIR__ . '/itm_dashboard_queries.php';

if (!function_exists('itm_patches_updates_open_due_join_sql')) {
    function itm_patches_updates_open_due_join_sql()
    {
        return ' LEFT JOIN patches_updates_status pus ON pus.id = pu.status_id AND pus.company_id = pu.company_id ';
    }
}

if (!function_exists('itm_patches_updates_open_due_where_sql')) {
    function itm_patches_updates_open_due_where_sql()
    {
        return ' pu.deleted_at IS NULL
          AND pu.due_date IS NOT NULL
          AND (pu.status_id IS NULL OR pus.is_closed = 0 OR pus.is_closed IS NULL) ';
    }
}

if (!function_exists('itm_patches_updates_assigned_employee_sql')) {
    function itm_patches_updates_assigned_employee_sql($employeeId, &$types, &$params)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return '';
        }
        $types .= 'i';
        $params[] = $employeeId;

        return ' AND pu.assigned_to_employee_id = ? ';
    }
}

if (!function_exists('itm_patches_updates_due_within_days_count')) {
    function itm_patches_updates_due_within_days_count($conn, $companyId, $days = 30, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        $employeeId = (int)$employeeId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return 0;
        }

        $types = 'ii';
        $params = [$companyId, $days];
        $assigneeSql = itm_patches_updates_assigned_employee_sql($employeeId, $types, $params);

        $sql = 'SELECT COUNT(*) AS cnt
                FROM patches_updates pu'
            . itm_patches_updates_open_due_join_sql()
            . ' WHERE pu.company_id = ?
                  AND ' . itm_patches_updates_open_due_where_sql()
            . ' AND pu.due_date >= CURDATE()
                  AND pu.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'
            . $assigneeSql;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
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

if (!function_exists('itm_patches_updates_due_trend')) {
    /**
     * @return array{labels:array<int,string>,data:array<int,int>}
     */
    function itm_patches_updates_due_trend($conn, $companyId, $days = 7, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        $employeeId = (int)$employeeId;
        $series = itm_dashboard_queries_last_n_day_labels($days);
        $data = [];
        foreach ($series['dates'] as $date) {
            $data[] = itm_patches_updates_due_on_date_count($conn, $companyId, $date, $employeeId);
        }

        return [
            'labels' => $series['labels'],
            'data' => $data,
        ];
    }
}

if (!function_exists('itm_patches_updates_due_on_date_count')) {
    function itm_patches_updates_due_on_date_count($conn, $companyId, $dueDate, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $dueDate = trim((string)$dueDate);
        $employeeId = (int)$employeeId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return 0;
        }

        $types = 'is';
        $params = [$companyId, $dueDate];
        $assigneeSql = itm_patches_updates_assigned_employee_sql($employeeId, $types, $params);

        $sql = 'SELECT COUNT(*) AS cnt
                FROM patches_updates pu'
            . itm_patches_updates_open_due_join_sql()
            . ' WHERE pu.company_id = ?
                  AND ' . itm_patches_updates_open_due_where_sql()
            . ' AND pu.due_date = ?'
            . $assigneeSql;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
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

if (!function_exists('itm_patches_updates_list_calendar_rows')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_patches_updates_list_calendar_rows($conn, $companyId, $startDate, $endDate)
    {
        $companyId = (int)$companyId;
        $startDate = trim((string)$startDate);
        $endDate = trim((string)$endDate);
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            return [];
        }

        $sql = 'SELECT pu.id, pu.hostname, pu.due_date
                FROM patches_updates pu'
            . itm_patches_updates_open_due_join_sql()
            . ' WHERE pu.company_id = ?
                  AND ' . itm_patches_updates_open_due_where_sql()
            . ' AND pu.due_date BETWEEN ? AND ?
                ORDER BY pu.due_date ASC, pu.id ASC';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iss', $companyId, $startDate, $endDate);
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

if (!function_exists('itm_patches_updates_integration_summary')) {
    /**
     * @return array<string,mixed>
     */
    function itm_patches_updates_integration_summary($conn, $companyId, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $base = defined('BASE_URL') ? BASE_URL : '';

        $due30 = itm_patches_updates_due_within_days_count($conn, $companyId, 30, $employeeId);
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $calendarMonth = 0;
        foreach (itm_patches_updates_list_calendar_rows($conn, $companyId, $monthStart, $monthEnd) as $row) {
            $calendarMonth++;
        }

        $calendarAllowed = function_exists('has_module_access')
            ? has_module_access($conn, $companyId, 'calendar')
            : true;
        $dashboardAllowed = true;
        $widgetAllowed = function_exists('itm_dashboard_widget_can_show')
            ? itm_dashboard_widget_can_show($conn, $companyId, $employeeId, 'patches_due_30d')
            : false;

        return [
            'due_within_30_days' => $due30,
            'due_this_month' => $calendarMonth,
            'calendar_allowed' => $calendarAllowed,
            'dashboard_allowed' => $dashboardAllowed,
            'widget_allowed' => $widgetAllowed,
            'calendar_url' => $base . 'modules/calendar/index.php',
            'dashboard_url' => $base . 'dashboard.php',
            'user_config_url' => $base . 'user-config.php#user-config-dashboard-widgets-form',
        ];
    }
}

if (!function_exists('itm_patches_updates_render_product_gaps_panel')) {
    function itm_patches_updates_render_product_gaps_panel($conn, $companyId, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return;
        }

        $summary = itm_patches_updates_integration_summary($conn, $companyId, $employeeId);
        $due30 = (int)($summary['due_within_30_days'] ?? 0);
        $dueMonth = (int)($summary['due_this_month'] ?? 0);
        $calendarUrl = (string)($summary['calendar_url'] ?? '');
        $dashboardUrl = (string)($summary['dashboard_url'] ?? '');
        $userConfigUrl = (string)($summary['user_config_url'] ?? '');
        $calendarAllowed = !empty($summary['calendar_allowed']);
        $dashboardAllowed = !empty($summary['dashboard_allowed']);
        $widgetAllowed = !empty($summary['widget_allowed']);
        ?>
        <section class="card itm-patches-product-gaps" style="margin-bottom:16px;" aria-labelledby="itm-patches-product-gaps-title">
            <div class="card-header">
                <strong id="itm-patches-product-gaps-title">Product gaps &amp; opportunities</strong>
            </div>
            <div class="itm-patches-product-gaps-body" style="padding:16px;">
                <h3 class="itm-patches-product-gaps-item-title" style="margin:0 0 8px;font-size:1rem;">Engage with dashboard and calendar</h3>
                <p class="form-hint" style="margin:0 0 12px;">
                    Set <strong>Due date</strong> and <strong>Assigned To</strong> on open patch rows (non-closed status). They appear on the company
                    <a href="<?php echo sanitize($calendarUrl); ?>" target="_blank" rel="noopener noreferrer">Calendar</a>
                    and count toward the <strong>Patches due in 30 days</strong> smart dashboard widget (assigned to you) when enabled on your profile.
                </p>
                <ul class="itm-patches-product-gaps-stats" style="margin:0 0 12px;padding-left:20px;">
                    <li><strong><?php echo sanitize((string)$dueMonth); ?></strong> open patch<?php echo $dueMonth === 1 ? '' : 'es'; ?> due this month (calendar feed)</li>
                    <li><strong><?php echo sanitize((string)$due30); ?></strong> open patch<?php echo $due30 === 1 ? '' : 'es'; ?> due within 30 days (dashboard widget metric)</li>
                </ul>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($calendarAllowed): ?>
                        <a class="btn btn-sm" href="<?php echo sanitize($calendarUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open calendar">📅</a>
                    <?php endif; ?>
                    <?php if ($dashboardAllowed): ?>
                        <a class="btn btn-sm" href="<?php echo sanitize($dashboardUrl); ?>" target="_blank" rel="noopener noreferrer" title="Open dashboard">📊</a>
                    <?php endif; ?>
                    <?php if ($widgetAllowed): ?>
                        <a class="btn btn-sm" href="<?php echo sanitize($userConfigUrl); ?>" target="_blank" rel="noopener noreferrer" title="Dashboard widget settings">⚙️</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('itm_patches_updates_module_access_allowed')) {
    function itm_patches_updates_module_access_allowed($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return false;
        }

        return !function_exists('has_module_access') || has_module_access($conn, $companyId, 'patches_updates');
    }
}

if (!function_exists('itm_patches_updates_fetch_for_equipment')) {
    /**
     * @return array<string,mixed>|null
     */
    function itm_patches_updates_fetch_for_equipment($conn, $companyId, $equipmentId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $equipmentId <= 0) {
            return null;
        }

        $sql = 'SELECT pu.*,
                       pus.name AS status_name,
                       pus.color AS status_color,
                       pus.is_closed AS status_is_closed,
                       pul.level AS level_name,
                       TRIM(CONCAT_WS(\' \', NULLIF(e.first_name, \'\'), NULLIF(e.last_name, \'\'))) AS assigned_to_name,
                       e.username AS assigned_to_username
                FROM patches_updates pu
                LEFT JOIN patches_updates_status pus
                    ON pus.id = pu.status_id AND pus.company_id = pu.company_id
                LEFT JOIN patches_updates_level pul
                    ON pul.id = pu.level_id AND pul.company_id = pu.company_id
                LEFT JOIN employees e
                    ON e.id = pu.assigned_to_employee_id AND e.company_id = pu.company_id
                WHERE pu.company_id = ? AND pu.equipment_id = ? AND pu.deleted_at IS NULL
                LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
        mysqli_stmt_execute($stmt);
        $row = null;
        if (function_exists('itm_mysqli_stmt_fetch_assoc')) {
            $row = itm_mysqli_stmt_fetch_assoc($stmt);
        } else {
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
        }
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_patches_updates_render_equipment_view_card')) {
    function itm_patches_updates_render_equipment_view_card($conn, $companyId, $equipmentId, $employeeId = 0)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        if (!($conn instanceof mysqli) || $companyId <= 0 || $equipmentId <= 0) {
            return;
        }
        if (!itm_patches_updates_module_access_allowed($conn, $companyId)) {
            return;
        }

        $patchRow = itm_patches_updates_fetch_for_equipment($conn, $companyId, $equipmentId);
        $base = defined('BASE_URL') ? BASE_URL : '';
        $createUrl = $base . 'modules/patches_updates/create.php?equipment_id=' . $equipmentId;
        ?>
        <div class="card" style="margin-top:20px;">
            <h2 style="margin-top:0;">Patches &amp; Updates</h2>
            <p style="margin:0 0 12px;color:#57606a;">Vulnerability and patch tracking linked to this equipment (one patch row per asset).</p>
            <?php if (!$patchRow): ?>
                <p style="margin:0;">No patch record is linked to this equipment yet.</p>
                <p style="margin:12px 0 0;">
                    <a class="btn btn-sm btn-primary" href="<?php echo sanitize($createUrl); ?>" title="Create patch record">➕</a>
                </p>
            <?php else: ?>
                <?php
                $patchId = (int)($patchRow['id'] ?? 0);
                $viewUrl = $base . 'modules/patches_updates/view.php?id=' . $patchId;
                $editUrl = $base . 'modules/patches_updates/edit.php?id=' . $patchId;
                $statusLabel = trim((string)($patchRow['status_name'] ?? ''));
                $statusColor = trim((string)($patchRow['status_color'] ?? ''));
                $assigneeLabel = trim((string)($patchRow['assigned_to_name'] ?? ''));
                if ($assigneeLabel === '') {
                    $assigneeLabel = trim((string)($patchRow['assigned_to_username'] ?? ''));
                }
                $summaryRows = [
                    'Status' => $statusLabel !== '' && function_exists('itm_crud_render_status_label_badge')
                        ? itm_crud_render_status_label_badge($statusLabel, $statusColor)
                        : sanitize($statusLabel !== '' ? $statusLabel : '—'),
                    'Assigned To' => sanitize($assigneeLabel !== '' ? $assigneeLabel : '—'),
                    'Level' => sanitize((string)($patchRow['level_name'] ?? '—')),
                    'Due date' => sanitize(itm_format_cell_scalar_display('due_date', $patchRow['due_date'] ?? '', 'patches_updates')),
                    'CVE' => sanitize((string)($patchRow['cve'] ?? '—')),
                    'Severity' => sanitize((string)($patchRow['severity'] ?? '—')),
                    'Operating system' => sanitize((string)($patchRow['operating_system'] ?? '—')),
                    'Scan date' => sanitize(itm_format_cell_scalar_display('date', $patchRow['date'] ?? '', 'patches_updates')),
                ];
                ?>
                <table><tbody>
                <?php foreach ($summaryRows as $label => $valueHtml): ?>
                    <?php if ($label !== 'Status' && trim(strip_tags((string)$valueHtml)) === '—') { continue; } ?>
                    <tr>
                        <th style="width:240px;"><?php echo sanitize($label); ?></th>
                        <td><?php echo $valueHtml; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
                <p style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-sm" href="<?php echo sanitize($viewUrl); ?>" title="View patch record">🔎</a>
                    <a class="btn btn-sm btn-primary" href="<?php echo sanitize($editUrl); ?>" title="Edit patch record">✏️</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
