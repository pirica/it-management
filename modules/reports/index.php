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
itm_ensure_upload_directory_chain(['reports_data']);

// Check authentication and permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: ../../login.php');
    exit;
}

$company_id = $_SESSION['company_id'] ?? null;
$current_user_id = $_SESSION['employee_id'] ?? null;
$current_role = $_SESSION['role_name'] ?? '';

// Check module access
if (!has_module_access($company_id, 'reports')) {
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

// Why: connection is handled by config/config.php and used by footer.php, do not close here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Hub - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/vendor/chart.js"></script>
    <link rel="stylesheet" href="../../css/reports/dashboard.css">
</head>
<body class="<?= isset($theme) ? $theme : 'light' ?>">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="reports-container">
        <!-- Header -->
        <header class="reports-header">
            <h1>📊 Reports Hub</h1>
            <div class="header-actions">
                <a href="../../modules/settings/index.php" class="btn btn-secondary">⚙️ Settings</a>
                <a href="../../logout.php" class="btn btn-danger">🚪 Logout</a>
            </div>
        </header>

        <!-- Quick Stats Cards -->
        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon">📦</div>
                <h3>Total Equipment</h3>
                <p class="stat-value"><?= array_sum($equipment_stats) ?></p>
            </article>
            <article class="stat-card">
                <div class="stat-icon">🎫</div>
                <h3>Open Tickets</h3>
                <p class="stat-value"><?= $ticket_data[0] ?? 0 ?></p>
            </article>
            <article class="stat-card">
                <div class="stat-icon">👥</div>
                <h3>Total Employees</h3>
                <p class="stat-value"><?= array_sum($hr_data) ?></p>
            </article>
            <article class="stat-card">
                <div class="stat-icon">🌐</div>
                <h3>Network Devices</h3>
                <p class="stat-value"><?= array_sum($network_data) ?></p>
            </article>
        </section>

        <!-- Main Dashboard Grid -->
        <section class="dashboard-grid">
            <!-- Equipment Reports -->
            <article class="report-card equipment-report">
                <h2>🔧 Equipment by Type</h2>
                <div class="chart-container">
                    <canvas id="equipmentChart"></canvas>
                </div>
                <p class="report-desc">Asset utilization by type (Servers, Workstations, Printers, Network Devices)</p>
            </article>

            <!-- Ticket Reports -->
            <article class="report-card tickets-report">
                <h2>🎫 Ticket Status</h2>
                <div class="chart-container">
                    <canvas id="ticketsChart"></canvas>
                </div>
                <p class="report-desc">Ticket distribution by status (Open, In Progress, Resolved, Closed)</p>
            </article>

            <!-- HR Reports -->
            <article class="report-card hr-report">
                <h2>👥 Employees by Department</h2>
                <div class="chart-container">
                    <canvas id="hrChart"></canvas>
                </div>
                <p class="report-desc">Employee distribution across departments (Engineering, IT, Sales, HR, Finance)</p>
            </article>

            <!-- Network Reports -->
            <article class="report-card networking-report">
                <h2>🌐 Network Device Types</h2>
                <div class="chart-container">
                    <canvas id="networkChart"></canvas>
                </div>
                <p class="report-desc">Device counts by type (Servers, Switches, Routers, Firewalls, Access Points)</p>
            </article>

            <!-- Budget Reports -->
            <article class="report-card budget-report">
                <h2>💵 Budget Categories</h2>
                <div class="chart-container">
                    <canvas id="budgetChart"></canvas>
                </div>
                <p class="report-desc">Budget allocation by category (Personnel, Equipment, Software, Services)</p>
            </article>

            <!-- Floor Plans -->
            <article class="report-card floorplans-report">
                <h2>📍 Equipment per Location</h2>
                <div class="chart-container">
                    <canvas id="floorplanChart"></canvas>
                </div>
                <p class="report-desc">Equipment count per location (Floor 1, Floor 2, Office A, Warehouse)</p>
            </article>

            <!-- Inventory -->
            <article class="report-card inventory-report">
                <h2>📦 Stock Level Distribution</h2>
                <div class="chart-container">
                    <canvas id="inventoryChart"></canvas>
                </div>
                <p class="report-desc">Items categorized by stock level (Low Stock, Normal, High)</p>
            </article>

            <!-- License Management -->
            <article class="report-card license-report">
                <h2>💾 License Status</h2>
                <div class="chart-container">
                    <canvas id="licenseChart"></canvas>
                </div>
                <p class="report-desc">License types and expiry status (Active, Expiring Soon, Expired)</p>
            </article>
        </section>

        <!-- Quick Actions -->
        <section class="reports-actions">
            <button onclick="exportAllReports()" class="btn btn-secondary">📥 Export Data</button>
        </section>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    // Chart.js configurations
    const chartConfig = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label || '';
                    }
                }
            }
        }
    };

    // Initialize charts after DOM load
    document.addEventListener('DOMContentLoaded', function() {
        // Equipment Chart - Asset count by type
        new Chart(document.getElementById('equipmentChart'), {
            type: 'bar',
            data: {
                labels: ['Servers', 'Workstations', 'Printers', 'Network Devices'],
                datasets: [{
                    label: 'Equipment Count',
                    data: <?= json_encode($equipment_stats) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            }
        });

        // Ticket Chart - Status distribution
        new Chart(document.getElementById('ticketsChart'), {
            type: 'pie',
            data: {
                labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                datasets: [{
                    data: <?= json_encode($ticket_data) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ]
                }]
            }
        });

        // HR Chart - Department distribution
        new Chart(document.getElementById('hrChart'), {
            type: 'doughnut',
            data: {
                labels: ['Engineering', 'IT', 'Sales', 'HR', 'Finance'],
                datasets: [{
                    data: <?= json_encode($hr_data) ?>,
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(201, 203, 207, 0.7)'
                    ]
                }]
            }
        });

        // Network Chart - Device types (radar)
        new Chart(document.getElementById('networkChart'), {
            type: 'radar',
            data: {
                labels: ['Servers', 'Switches', 'Routers', 'Firewalls', 'Access Points'],
                datasets: [{
                    label: 'Device Count',
                    data: <?= json_encode($network_data) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.4)',
                    borderColor: 'rgb(75, 192, 192)',
                    pointBackgroundColor: 'rgb(75, 192, 192)'
                }]
            }
        });

        // Budget Chart - Category allocation (pie)
        new Chart(document.getElementById('budgetChart'), {
            type: 'pie',
            data: {
                labels: ['Personnel', 'Equipment', 'Software', 'Services', 'Other'],
                datasets: [{
                    data: <?= json_encode($budget_data) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ]
                }]
            }
        });

        // Floor Plans Chart - Location equipment count (bar)
        new Chart(document.getElementById('floorplanChart'), {
            type: 'bar',
            data: {
                labels: ['Floor 1', 'Floor 2', 'Office A', 'Warehouse'],
                datasets: [{
                    label: 'Equipment Count',
                    data: <?= json_encode($floorplan_data) ?>,
                    backgroundColor: 'rgba(255, 206, 86, 0.6)'
                }]
            }
        });

        // Inventory Chart - Stock levels (line)
        new Chart(document.getElementById('inventoryChart'), {
            type: 'line',
            data: {
                labels: ['Low Stock', 'Normal', 'High'],
                datasets: [{
                    label: 'Items by Level',
                    data: <?= json_encode($inventory_data) ?>,
                    borderColor: 'rgba(255, 159, 64, 0.8)',
                    backgroundColor: 'rgba(255, 159, 64, 0.2)'
                }]
            }
        });

        // License Chart - License status (bar)
        new Chart(document.getElementById('licenseChart'), {
            type: 'bar',
            data: {
                labels: ['Active', 'Expiring Soon', 'Expired'],
                datasets: [{
                    label: 'License Count',
                    data: <?= json_encode($license_data) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                }]
            }
        });
    });

    // Export functionality
    function exportAllReports() {
        alert('Export would generate CSV/PDF of chart data. Implementation pending.');
    }
    </script>
</body>
</html>
