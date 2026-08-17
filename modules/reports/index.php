<?php
/**
 * Reports Hub - Main Dashboard
 * @file reports/index.php
 * 
 * Visual dashboard using existing IT Management tables from db/.
 * No new database schema required - queries existing tables directly.
 */

// Include shared configuration and helpers
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/bootstrap_helpers.php';

// Ensure required directories exist
itm_ensure_upload_directory_chain(ROOT_PATH . 'reports_data');

$company_id = $_SESSION['company_id'] ?? null;
$current_user_id = $_SESSION['employee_id'] ?? null;
$current_role = $_SESSION['role_name'] ?? '';

// Check module access
if (!has_module_access($conn, $company_id, 'reports')) {
    header('Location: ../../modules/company_module_access/index.php');
    exit;
}

require_once ROOT_PATH . 'includes/itm_scheduled_reports.php';
$scheduledReportsFlash = '';
$scheduledReportsList = [];
$isReportsAdmin = function_exists('itm_is_admin') && itm_is_admin();

if ($isReportsAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scheduled_report_action'])) {
    itm_require_post_csrf();
    $action = (string) ($_POST['scheduled_report_action'] ?? '');
    if ($action === 'save_scheduled_report') {
        $reportSlug = trim((string) ($_POST['report_slug'] ?? ''));
        $scheduleCron = trim((string) ($_POST['schedule_cron'] ?? ''));
        $format = strtolower((string) ($_POST['format'] ?? 'pdf'));
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $recipientsRaw = trim((string) ($_POST['recipients'] ?? ''));
        $recipients = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $recipientsRaw))));
        $catalog = itm_scheduled_reports_catalog();
        if (!isset($catalog[$reportSlug])) {
            $scheduledReportsFlash = 'Unknown report slug.';
        } elseif ($scheduleCron === '' || count(preg_split('/\s+/', $scheduleCron)) !== 5) {
            $scheduledReportsFlash = 'Cron must have five fields (minute hour day month weekday).';
        } elseif ($recipients === []) {
            $scheduledReportsFlash = 'Add at least one recipient email.';
        } elseif (!in_array($format, ['pdf', 'xlsx'], true)) {
            $scheduledReportsFlash = 'Invalid export format.';
        } else {
            $recipientsJson = json_encode($recipients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $editId = (int) ($_POST['scheduled_report_id'] ?? 0);
            if ($editId > 0) {
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE scheduled_reports SET report_slug = ?, schedule_cron = ?, recipients_json = ?, format = ?, enabled = ?, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
                );
                if ($stmt) {
                    $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
                    mysqli_stmt_bind_param($stmt, 'ssssiiii', $reportSlug, $scheduleCron, $recipientsJson, $format, $enabled, $employeeId, $editId, $company_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $scheduledReportsFlash = 'Schedule updated.';
                }
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO scheduled_reports (company_id, report_slug, schedule_cron, recipients_json, format, enabled, created_by, active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())'
                );
                if ($stmt) {
                    $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
                    mysqli_stmt_bind_param($stmt, 'issssii', $company_id, $reportSlug, $scheduleCron, $recipientsJson, $format, $enabled, $employeeId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $scheduledReportsFlash = 'Schedule created.';
                }
            }
        }
    } elseif ($action === 'delete_scheduled_report') {
        $deleteId = (int) ($_POST['scheduled_report_id'] ?? 0);
        if ($deleteId > 0 && function_exists('itm_crud_build_soft_delete_sql')) {
            $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
            $whereSql = 'id = ' . $deleteId . ' AND company_id = ' . (int) $company_id;
            $sql = itm_crud_build_soft_delete_sql('scheduled_reports', $whereSql, $employeeId);
            itm_run_query($conn, $sql);
            $scheduledReportsFlash = 'Schedule removed.';
        }
    }
}

if ($isReportsAdmin) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT * FROM scheduled_reports WHERE company_id = ? AND deleted_at IS NULL ORDER BY id DESC'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $scheduledReportsList[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Load chart data using helpers from api/helpers.php
require_once __DIR__ . '/api/helpers.php';

$equipment_stats = get_equipment_statistics();
$ticket_data = get_ticket_statistics();
$hr_data = get_hr_statistics();
$network_data = get_network_device_counts();
$budget_data = get_budget_statistics();
$floorplan_data = get_floorplan_location_data();
$inventory_data = get_inventory_stock_levels();
$license_data = get_license_statistics();

$equipment_status_data = get_equipment_status_statistics();
$asset_additions_data = get_monthly_asset_additions();
$assets_by_dept_data = get_assets_by_department();

// Hotel Operations Data
$ops_summary = get_ops_summary_metrics();
$ops_occupancy_trend = get_ops_occupancy_30day();
$ops_revenue_yoy = get_ops_monthly_revenue_yoy();
$ops_revenue_mix = get_ops_revenue_mix_mtd();
$ops_fb_covers = get_ops_fb_outlet_covers();

// Advanced Budgeting & Insights
$budget_vs_actual = get_budget_vs_actual_trend();
$budget_by_dept = get_budget_by_department();
$budget_yoy = get_budget_yoy_comparison();
$asset_value = get_asset_financial_value();
$maintenance_forecast = get_upcoming_maintenance_forecast();
$employee_growth = get_employee_growth_trend();
$monthly_comparison = get_monthly_actual_comparison();
$csat_trend = get_ticket_csat_trend();
$asset_lifecycle_summary = get_asset_lifecycle_stage_summary();

// Summary Metrics
$total_budget = array_sum($budget_vs_actual['budget']);
$total_actual = array_sum($budget_vs_actual['actual']);
$utilization_pct = $total_budget > 0 ? round(($total_actual / $total_budget) * 100, 1) : 0;
$budget_remaining = $total_budget - $total_actual;

$open_tickets = 0;
foreach ($ticket_data['labels'] as $idx => $label) {
    if (in_array(strtolower($label), ['open', 'in progress', 'new'])) {
        $open_tickets += $ticket_data['data'][$idx];
    }
}

// Why: connection is handled by config/config.php and used by footer.php, do not close here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Reports';
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
        $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/reports/dashboard.css">
    <script src="<?php echo BASE_URL; ?>js/vendor/chart.js"></script>
    <style>
        .reports-hub-header {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            min-height: 40px;
        }
        .reports-hub-title {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            margin: 0;
            text-align: center;
        }
        .report-section {
            margin-bottom: 50px;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--accent);
            color: var(--text-primary);
        }
        .section-header h2 {
            margin: 0;
            font-size: 1.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .insight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .insight-card {
            background: var(--bg-secondary);
            padding: 18px;
            border-radius: 10px;
            border-left: 5px solid var(--accent);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        .insight-card:hover {
            transform: translateY(-3px);
        }
        .insight-card h4 {
            margin: 0 0 8px 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        .insight-value {
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--text-primary);
        }
        .comparison-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        .comparison-badge.up { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .comparison-badge.down { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    </style>
</head>
<body class="<?php echo isset($theme) ? $theme : 'light'; ?>">
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../../includes/header.php'; ?>

            <div class="content">
                <!-- Header -->
                <div class="reports-hub-header">
                    <h1 class="reports-hub-title">📊 Reports Hub</h1>
                </div>

                <?php if ($isReportsAdmin): ?>
                <section class="card" style="margin-bottom:24px;">
                    <h2 style="margin-top:0;">Scheduled executive reports</h2>
                    <?php if ($scheduledReportsFlash !== ''): ?>
                        <p><?php echo sanitize($scheduledReportsFlash); ?></p>
                    <?php endif; ?>
                    <table class="table" style="width:100%;margin-bottom:16px;" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Cron</th>
                                <th>Format</th>
                                <th>Enabled</th>
                                <th>Last sent</th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($scheduledReportsList): ?>
                            <?php foreach ($scheduledReportsList as $sr): ?>
                                <?php $catalog = itm_scheduled_reports_catalog(); ?>
                                <tr>
                                    <td><?php echo sanitize($catalog[$sr['report_slug']] ?? $sr['report_slug']); ?></td>
                                    <td><code><?php echo sanitize($sr['schedule_cron']); ?></code></td>
                                    <td><?php echo sanitize(strtoupper((string) $sr['format'])); ?></td>
                                    <td><?php echo (int) $sr['enabled'] === 1 ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo $sr['last_sent_at'] ? sanitize(itm_format_cell_scalar_display($sr['last_sent_at'])) : '—'; ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <button type="button" class="btn btn-sm js-edit-schedule" title="Edit"
                                            data-id="<?php echo (int) $sr['id']; ?>"
                                            data-slug="<?php echo sanitize($sr['report_slug']); ?>"
                                            data-cron="<?php echo sanitize($sr['schedule_cron']); ?>"
                                            data-format="<?php echo sanitize($sr['format']); ?>"
                                            data-enabled="<?php echo (int) $sr['enabled']; ?>"
                                            data-recipients="<?php echo sanitize(implode(', ', json_decode((string) $sr['recipients_json'], true) ?: [])); ?>">✏️</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this schedule?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                            <input type="hidden" name="scheduled_report_action" value="delete_scheduled_report">
                                            <input type="hidden" name="scheduled_report_id" value="<?php echo (int) $sr['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No schedules yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary" id="open-schedule-modal" title="Schedule report">➕</button>
                </section>

                <div id="schedule-report-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
                    <div class="card" style="max-width:520px;width:92%;margin:auto;margin-top:10vh;">
                        <h2 style="margin-top:0;">Schedule report</h2>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                            <input type="hidden" name="scheduled_report_action" value="save_scheduled_report">
                            <input type="hidden" name="scheduled_report_id" id="schedule-report-id" value="0">
                            <div class="form-group">
                                <label>Report</label>
                                <select name="report_slug" id="schedule-report-slug" required>
                                    <?php foreach (itm_scheduled_reports_catalog() as $slug => $label): ?>
                                        <option value="<?php echo sanitize($slug); ?>"><?php echo sanitize($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cron (minute hour dom month dow)</label>
                                <input type="text" name="schedule_cron" id="schedule-report-cron" placeholder="0 8 * * 1" required>
                            </div>
                            <div class="form-group">
                                <label>Recipients (comma-separated emails)</label>
                                <input type="text" name="recipients" id="schedule-report-recipients" required>
                            </div>
                            <div class="form-group">
                                <label>Format</label>
                                <select name="format" id="schedule-report-format">
                                    <option value="pdf">PDF (HTML email + attachment)</option>
                                    <option value="xlsx">XLSX (CSV attachment)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="enabled" id="schedule-report-enabled" value="1" checked>
                                    <span>Enabled</span>
                                </label>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                                <button type="button" class="btn" id="close-schedule-modal" title="Cancel">🔙</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Stats Cards -->
                <section class="stats-grid">
                    <article class="stat-card">
                        <div class="stat-icon">📦</div>
                        <h3>Total Assets</h3>
                        <p class="stat-value"><?php echo number_format(array_sum($equipment_stats['data'])); ?></p>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon">🎫</div>
                        <h3>Open Tickets</h3>
                        <p class="stat-value"><?php echo number_format($open_tickets); ?></p>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon">💵</div>
                        <h3>Budget Utilization</h3>
                        <p class="stat-value"><?php echo $utilization_pct; ?>%</p>
                    </article>
                    <article class="stat-card">
                        <div class="stat-icon">👥</div>
                        <h3>Workforce</h3>
                        <p class="stat-value"><?php echo number_format(array_sum($hr_data['data'])); ?></p>
                    </article>
                </section>

                <!-- Hotel Operations Section -->
                <section class="report-section">
                    <div class="section-header">
                        <span>🏨</span>
                        <h2>Hotel Operations</h2>
                    </div>

                    <div class="insight-grid">
                        <div class="insight-card" style="border-left-color: #3b82f6;">
                            <h4>MTD Avg Occupancy</h4>
                            <div class="insight-value"><?php echo number_format($ops_summary['avg_occupancy'], 1); ?>%</div>
                        </div>
                        <div class="insight-card" style="border-left-color: #10b981;">
                            <h4>MTD Avg ADR</h4>
                            <div class="insight-value">$<?php echo number_format($ops_summary['avg_adr'], 2); ?></div>
                        </div>
                        <div class="insight-card" style="border-left-color: #f59e0b;">
                            <h4>MTD Avg RevPAR</h4>
                            <div class="insight-value">$<?php echo number_format($ops_summary['avg_revpar'], 2); ?></div>
                        </div>
                        <div class="insight-card" style="border-left-color: #ef4444;">
                            <h4>MTD Total Revenue</h4>
                            <div class="insight-value">$<?php echo number_format($ops_summary['total_revenue'], 0); ?></div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <article class="report-card">
                            <h2>📈 30-Day Occupancy Trend</h2>
                            <div class="chart-container">
                                <canvas id="opsOccupancyChart"></canvas>
                            </div>
                            <p class="report-desc">Daily occupancy percentage over the last 30 operational days.</p>
                        </article>

                        <article class="report-card">
                            <h2>📊 Revenue YoY Comparison</h2>
                            <div class="chart-container">
                                <canvas id="opsRevenueYoyChart"></canvas>
                            </div>
                            <p class="report-desc">Monthly total revenue comparison: This Year vs Last Year.</p>
                        </article>

                        <article class="report-card">
                            <h2>🍕 Revenue Mix (MTD)</h2>
                            <div class="chart-container">
                                <canvas id="opsRevenueMixChart"></canvas>
                            </div>
                            <p class="report-desc">Distribution of revenue across different departments for the current month.</p>
                        </article>

                        <article class="report-card">
                            <h2>🍽️ F&B Outlet Covers (MTD)</h2>
                            <div class="chart-container">
                                <canvas id="opsFbCoversChart"></canvas>
                            </div>
                            <p class="report-desc">Total covers by F&B outlet and meal period for the current month.</p>
                        </article>
                    </div>
                </section>

                <!-- Financial Performance Section -->
                <section class="report-section">
                    <div class="section-header">
                        <span>💰</span>
                        <h2>Financial Performance</h2>
                    </div>

                    <div class="insight-grid">
                        <div class="insight-card">
                            <h4>Current Year Budget</h4>
                            <div class="insight-value">$<?php echo number_format($total_budget, 2); ?></div>
                        </div>
                        <div class="insight-card" style="border-left-color: #f59e0b;">
                            <h4>Actual YTD Spend</h4>
                            <div class="insight-value">$<?php echo number_format($total_actual, 2); ?></div>
                        </div>
                        <div class="insight-card" style="border-left-color: #10b981;">
                            <h4>Remaining Funds</h4>
                            <div class="insight-value">$<?php echo number_format($budget_remaining, 2); ?></div>
                        </div>
                        <div class="insight-card" style="border-left-color: #6366f1;">
                            <h4>Spend Comparison (<?php echo $monthly_comparison['month_name']; ?>)</h4>
                            <div class="insight-value">$<?php echo number_format($monthly_comparison['this_year'], 2); ?></div>
                            <?php
                                $diff = $monthly_comparison['this_year'] - $monthly_comparison['last_year'];
                                $pct = $monthly_comparison['last_year'] > 0 ? ($diff / $monthly_comparison['last_year']) * 100 : 0;
                                $class = $diff > 0 ? 'up' : 'down';
                                $arrow = $diff > 0 ? '▲' : '▼';
                            ?>
                            <div class="comparison-badge <?php echo $class; ?>">
                                <?php echo $arrow; ?> <?php echo abs(round($pct, 1)); ?>% vs last year
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <article class="report-card">
                            <h2>📉 Budget vs Actual Trend</h2>
                            <div class="chart-container">
                                <canvas id="budgetVsActualChart"></canvas>
                            </div>
                            <p class="report-desc">Monthly comparison of planned budget vs real expenses for the current year.</p>
                        </article>

                        <article class="report-card">
                            <h2>🏢 Budget by Department</h2>
                            <div class="chart-container">
                                <canvas id="budgetByDeptChart"></canvas>
                            </div>
                            <p class="report-desc">Annual budget distribution across company departments.</p>
                        </article>

                        <article class="report-card">
                            <h2>📊 Year-over-Year Budget</h2>
                            <div class="chart-container">
                                <canvas id="budgetYoyChart"></canvas>
                            </div>
                            <p class="report-desc">Comparison of total annual budget: Last Year vs Current Year.</p>
                        </article>

                        <article class="report-card">
                            <h2>💎 Asset Inventory Value</h2>
                            <div class="chart-container">
                                <canvas id="assetValueChart"></canvas>
                            </div>
                            <p class="report-desc">Total financial value of equipment based on purchase cost.</p>
                        </article>
                    </div>
                </section>

                <!-- Infrastructure & Assets Section -->
                <section class="report-section">
                    <div class="section-header">
                        <span>🏗️</span>
                        <h2>Infrastructure & Assets</h2>
                    </div>
                    <div class="dashboard-grid">
                        <article class="report-card">
                            <h2>📦 Asset Distribution by Type</h2>
                            <div class="chart-container">
                                <canvas id="equipmentChart"></canvas>
                            </div>
                            <p class="report-desc">Asset distribution by equipment category (equipment types).</p>
                        </article>

                        <article class="report-card">
                            <h2>🌐 Network Ecosystem</h2>
                            <div class="chart-container">
                                <canvas id="networkChart"></canvas>
                            </div>
                            <p class="report-desc">Device counts for critical networking infrastructure.</p>
                        </article>

                        <article class="report-card">
                            <h2>📍 Location Density</h2>
                            <div class="chart-container">
                                <canvas id="floorplanChart"></canvas>
                            </div>
                            <p class="report-desc">Equipment concentration across different site locations.</p>
                        </article>

                        <article class="report-card">
                            <h2>📦 Stock Health</h2>
                            <div class="chart-container">
                                <canvas id="inventoryChart"></canvas>
                            </div>
                            <p class="report-desc">Inventory levels relative to defined minimum thresholds.</p>
                        </article>

                        <article class="report-card">
                            <h2>🔄 Asset Status</h2>
                            <div class="chart-container">
                                <canvas id="assetStatusChart"></canvas>
                            </div>
                            <p class="report-desc">Distribution of equipment by current operational status.</p>
                        </article>

                        <article class="report-card">
                            <h2>📅 Monthly Asset Additions</h2>
                            <div class="chart-container">
                                <canvas id="assetAdditionsChart"></canvas>
                            </div>
                            <p class="report-desc">Number of new assets acquired per month over the past year.</p>
                        </article>

                        <article class="report-card">
                            <h2>🏢 Assets by Department</h2>
                            <div class="chart-container">
                                <canvas id="assetsByDeptChart"></canvas>
                            </div>
                            <p class="report-desc">Equipment allocation across company departments.</p>
                        </article>

                        <article class="report-card">
                            <h2>♻️ Asset Lifecycle Stages</h2>
                            <div class="chart-container">
                                <canvas id="assetLifecycleChart"></canvas>
                            </div>
                            <p class="report-desc">Equipment counts by lifecycle stage (procurement through disposal).</p>
                        </article>
                    </div>
                </section>

                <!-- Operations & Compliance Section -->
                <section class="report-section">
                    <div class="section-header">
                        <span>📂</span>
                        <h2>Operations & Compliance</h2>
                    </div>
                    <div class="dashboard-grid">
                        <article class="report-card">
                            <h2>🎫 Support Ticket Status</h2>
                            <div class="chart-container">
                                <canvas id="ticketsChart"></canvas>
                            </div>
                            <p class="report-desc">Current state of IT support requests.</p>
                        </article>

                        <article class="report-card">
                            <h2>⭐ Ticket CSAT Trend</h2>
                            <div class="chart-container">
                                <canvas id="csatTrendChart"></canvas>
                            </div>
                            <p class="report-desc">Average customer satisfaction score by month (last 12 months).</p>
                        </article>

                        <article class="report-card">
                            <h2>💾 Software Licensing</h2>
                            <div class="chart-container">
                                <canvas id="licenseChart"></canvas>
                            </div>
                            <p class="report-desc">License status including expiries and active subscriptions.</p>
                        </article>

                        <article class="report-card" style="grid-column: span 2;">
                            <h2>📅 Maintenance & Expiry Forecast (6 Months)</h2>
                            <div class="chart-container" style="height: 350px;">
                                <canvas id="maintenanceForecastChart"></canvas>
                            </div>
                            <p class="report-desc">Upcoming warranty and license expiries requiring action.</p>
                        </article>
                    </div>
                </section>

                <!-- Workforce Section -->
                <section class="report-section">
                    <div class="section-header">
                        <span>👥</span>
                        <h2>Human Resources</h2>
                    </div>
                    <div class="dashboard-grid">
                        <article class="report-card">
                            <h2>🏢 Department Distribution</h2>
                            <div class="chart-container">
                                <canvas id="hrChart"></canvas>
                            </div>
                            <p class="report-desc">Headcount distribution across company departments.</p>
                        </article>

                        <article class="report-card">
                            <h2>📈 Hiring Trend</h2>
                            <div class="chart-container">
                                <canvas id="employeeGrowthChart"></canvas>
                            </div>
                            <p class="report-desc">New hire volume over the past 12 months.</p>
                        </article>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="reports-actions">
                    <button onclick="exportAllReports()" class="btn btn-secondary">📥 Export All</button>
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
                </section>
            </div>
        </div>
    </div>

    <script>
    // Global Chart.js defaults
    const isDark = document.body.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
    const textColor = isDark ? '#e1e1e1' : '#333';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Segoe UI', 'Roboto', sans-serif";

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                grid: { color: gridColor },
                ticks: { color: textColor }
            },
            x: {
                grid: { display: false },
                ticks: { color: textColor }
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') return;

        // --- HOTEL OPERATIONS CHARTS ---

        new Chart(document.getElementById('opsOccupancyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($ops_occupancy_trend['labels']); ?>,
                datasets: [{
                    label: 'Occupancy %',
                    data: <?php echo json_encode($ops_occupancy_trend['data']); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: Object.assign({}, baseOptions, {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: value => value + '%' }
                    }
                }
            })
        });

        new Chart(document.getElementById('opsRevenueYoyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ops_revenue_yoy['labels']); ?>,
                datasets: [
                    {
                        label: 'This Year',
                        data: <?php echo json_encode($ops_revenue_yoy['this_year']); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Last Year',
                        data: <?php echo json_encode($ops_revenue_yoy['last_year']); ?>,
                        backgroundColor: '#94a3b8',
                        borderRadius: 4
                    }
                ]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: true, position: 'top' } }
            })
        });

        new Chart(document.getElementById('opsRevenueMixChart'), {
            type: 'polarArea',
            data: {
                labels: <?php echo json_encode($ops_revenue_mix['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($ops_revenue_mix['data']); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(99, 102, 241, 0.7)',
                        'rgba(236, 72, 153, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'right' } },
                scales: {
                    r: {
                        grid: { color: gridColor },
                        ticks: { display: false }
                    }
                }
            }
        });

        new Chart(document.getElementById('opsFbCoversChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ops_fb_covers['labels']); ?>,
                datasets: [
                    {
                        label: 'Breakfast',
                        data: <?php echo json_encode($ops_fb_covers['breakfast']); ?>,
                        backgroundColor: '#fbbf24'
                    },
                    {
                        label: 'Lunch',
                        data: <?php echo json_encode($ops_fb_covers['lunch']); ?>,
                        backgroundColor: '#60a5fa'
                    },
                    {
                        label: 'Dinner',
                        data: <?php echo json_encode($ops_fb_covers['dinner']); ?>,
                        backgroundColor: '#34d399'
                    }
                ]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: true, position: 'top' } },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, grid: { color: gridColor } }
                }
            })
        });

        // --- FINANCIAL CHARTS ---

        new Chart(document.getElementById('budgetVsActualChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($budget_vs_actual['labels']); ?>,
                datasets: [
                    {
                        label: 'Budget',
                        data: <?php echo json_encode($budget_vs_actual['budget']); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Actual',
                        data: <?php echo json_encode($budget_vs_actual['actual']); ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: true, position: 'top' } }
            })
        });

        new Chart(document.getElementById('budgetByDeptChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($budget_by_dept['labels']); ?>,
                datasets: [{
                    label: 'Annual Budget',
                    data: <?php echo json_encode($budget_by_dept['data']); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('budgetYoyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($budget_yoy['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($budget_yoy['data']); ?>,
                    backgroundColor: ['#64748b', '#3b82f6'],
                    borderRadius: 8,
                    barThickness: 60
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('assetValueChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($asset_value['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($asset_value['data']); ?>,
                    backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981', '#6366f1', '#ec4899', '#8b5cf6']
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'right' } },
                maintainAspectRatio: false
            }
        });

        // --- INFRASTRUCTURE CHARTS ---

        new Chart(document.getElementById('equipmentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($equipment_stats['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($equipment_stats['data']); ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('networkChart'), {
            type: 'radar',
            data: {
                labels: <?php echo json_encode($network_data['labels']); ?>,
                datasets: [{
                    label: 'Devices',
                    data: <?php echo json_encode($network_data['data']); ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: '#6366f1',
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        grid: { color: gridColor },
                        pointLabels: { color: textColor }
                    }
                },
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('floorplanChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($floorplan_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($floorplan_data['data']); ?>,
                    backgroundColor: '#f59e0b',
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('inventoryChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($inventory_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($inventory_data['data']); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('assetStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($equipment_status_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($equipment_status_data['data']); ?>,
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#6366f1', '#8b5cf6', '#ec4899']
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'right' } },
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('assetAdditionsChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($asset_additions_data['labels']); ?>,
                datasets: [{
                    label: 'New Assets',
                    data: <?php echo json_encode($asset_additions_data['data']); ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('assetsByDeptChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($assets_by_dept_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($assets_by_dept_data['data']); ?>,
                    backgroundColor: '#f59e0b',
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('assetLifecycleChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($asset_lifecycle_summary['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($asset_lifecycle_summary['data']); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#94a3b8', '#6366f1']
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'right' } },
                maintainAspectRatio: false
            }
        });

        // --- OPERATIONS CHARTS ---

        new Chart(document.getElementById('ticketsChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($ticket_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($ticket_data['data']); ?>,
                    backgroundColor: ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#64748b']
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'right' } },
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('csatTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($csat_trend['labels']); ?>,
                datasets: [{
                    label: 'Avg CSAT',
                    data: <?php echo json_encode($csat_trend['data']); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.15)',
                    fill: true,
                    tension: 0.35,
                    spanGaps: true,
                    pointRadius: 4
                }]
            },
            options: Object.assign({}, baseOptions, {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        grid: { color: gridColor },
                        ticks: { color: textColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            })
        });

        new Chart(document.getElementById('licenseChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($license_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($license_data['data']); ?>,
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderRadius: 5
                }]
            },
            options: baseOptions
        });

        new Chart(document.getElementById('maintenanceForecastChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($maintenance_forecast['labels']); ?>,
                datasets: [
                    {
                        label: 'Warranty Expiries',
                        data: <?php echo json_encode($maintenance_forecast['warranty']); ?>,
                        backgroundColor: '#6366f1'
                    },
                    {
                        label: 'License Expiries',
                        data: <?php echo json_encode($maintenance_forecast['licenses']); ?>,
                        backgroundColor: '#ec4899'
                    }
                ]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: true, position: 'top' } },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, grid: { color: gridColor } }
                }
            })
        });

        // --- WORKFORCE CHARTS ---

        new Chart(document.getElementById('hrChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($hr_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($hr_data['data']); ?>,
                    backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981', '#6366f1', '#ec4899', '#8b5cf6']
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'right' } },
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('employeeGrowthChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($employee_growth['labels']); ?>,
                datasets: [{
                    label: 'New Hires',
                    data: <?php echo json_encode($employee_growth['data']); ?>,
                    borderColor: '#8b5cf6',
                    pointBackgroundColor: '#8b5cf6',
                    tension: 0.4
                }]
            },
            options: baseOptions
        });
    });

    function exportAllReports() {
        alert('All report data prepared for export. Generating combined PDF/XLSX... (Simulated)');
    }

    (function () {
        var modal = document.getElementById('schedule-report-modal');
        if (!modal) return;
        var openBtn = document.getElementById('open-schedule-modal');
        var closeBtn = document.getElementById('close-schedule-modal');
        function openModal(reset) {
            if (reset) {
                document.getElementById('schedule-report-id').value = '0';
                document.getElementById('schedule-report-cron').value = '0 8 * * 1';
                document.getElementById('schedule-report-recipients').value = '';
                document.getElementById('schedule-report-enabled').checked = true;
            }
            modal.style.display = 'flex';
        }
        function closeModal() { modal.style.display = 'none'; }
        if (openBtn) openBtn.addEventListener('click', function () { openModal(true); });
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        document.querySelectorAll('.js-edit-schedule').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('schedule-report-id').value = btn.getAttribute('data-id') || '0';
                document.getElementById('schedule-report-slug').value = btn.getAttribute('data-slug') || '';
                document.getElementById('schedule-report-cron').value = btn.getAttribute('data-cron') || '';
                document.getElementById('schedule-report-format').value = btn.getAttribute('data-format') || 'pdf';
                document.getElementById('schedule-report-recipients').value = btn.getAttribute('data-recipients') || '';
                document.getElementById('schedule-report-enabled').checked = (btn.getAttribute('data-enabled') === '1');
                openModal(false);
            });
        });
    })();
    </script>
</body>
</html>
