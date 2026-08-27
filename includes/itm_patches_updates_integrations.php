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

if (!function_exists('itm_patches_updates_due_within_days_count')) {
    function itm_patches_updates_due_within_days_count($conn, $companyId, $days = 30)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        if (!($conn instanceof mysqli) || $companyId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM patches_updates pu'
            . itm_patches_updates_open_due_join_sql()
            . ' WHERE pu.company_id = ?
                  AND ' . itm_patches_updates_open_due_where_sql()
            . ' AND pu.due_date >= CURDATE()
                  AND pu.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $days);
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
    function itm_patches_updates_due_trend($conn, $companyId, $days = 7)
    {
        $companyId = (int)$companyId;
        $days = max(1, (int)$days);
        $series = itm_dashboard_queries_last_n_day_labels($days);
        $data = [];
        foreach ($series['dates'] as $date) {
            $data[] = itm_patches_updates_due_on_date_count($conn, $companyId, $date);
        }

        return [
            'labels' => $series['labels'],
            'data' => $data,
        ];
    }
}

if (!function_exists('itm_patches_updates_due_on_date_count')) {
    function itm_patches_updates_due_on_date_count($conn, $companyId, $dueDate)
    {
        $companyId = (int)$companyId;
        $dueDate = trim((string)$dueDate);
        if (!($conn instanceof mysqli) || $companyId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS cnt
                FROM patches_updates pu'
            . itm_patches_updates_open_due_join_sql()
            . ' WHERE pu.company_id = ?
                  AND ' . itm_patches_updates_open_due_where_sql()
            . ' AND pu.due_date = ?';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $dueDate);
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

        $due30 = itm_patches_updates_due_within_days_count($conn, $companyId, 30);
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
        $widgetAllowed = !empty($summary['widget_allowed']);
        ?>
        <section class="card itm-patches-product-gaps" style="margin-bottom:16px;" aria-labelledby="itm-patches-product-gaps-title">
            <div class="card-header">
                <strong id="itm-patches-product-gaps-title">Product gaps &amp; opportunities</strong>
            </div>
            <div class="itm-patches-product-gaps-body" style="padding:16px;">
                <h3 class="itm-patches-product-gaps-item-title" style="margin:0 0 8px;font-size:1rem;">Engage with dashboard and calendar</h3>
                <p class="form-hint" style="margin:0 0 12px;">
                    Set <strong>Due date</strong> on open patch rows (non-closed status). They appear on the company
                    <a href="<?php echo sanitize($calendarUrl); ?>" target="_blank" rel="noopener noreferrer">Calendar</a>
                    and count toward the <strong>Patches due in 30 days</strong> smart dashboard widget when enabled on your profile.
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
