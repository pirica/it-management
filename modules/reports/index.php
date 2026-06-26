<?php
/**
 * Reports Hub - Main Dashboard
 * @file reports/index.php
 * 
 * Visual dashboard using existing IT Management tables from database.sql.
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

// Advanced Budgeting & Insights
$budget_vs_actual = get_budget_vs_actual_trend();
$budget_by_dept = get_budget_by_department();
$budget_yoy = get_budget_yoy_comparison();
$asset_value = get_asset_financial_value();
$maintenance_forecast = get_upcoming_maintenance_forecast();
$employee_growth = get_employee_growth_trend();
$monthly_comparison = get_monthly_actual_comparison();

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
    <title>Reports Hub - <?php echo sanitize($app_name); ?></title>
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
                    <button onclick="exportAllReports()" class="btn btn-secondary">📥 Export All Reports</button>
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Print Dashboard</button>
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
    </script>
</body>
</html>
