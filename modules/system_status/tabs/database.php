<?php
/**
 * Database Tab
 *
 * MySQL metrics from the active mysqli connection (server-rendered).
 */

require_once dirname(__DIR__, 3) . '/includes/itm_system_status_native.php';

$db_metrics = [];
$total_size = 0;
$query = "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'SizeMB'
          FROM information_schema.TABLES
          GROUP BY table_schema
          ORDER BY SizeMB DESC";
$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $db_metrics[] = $row;
        $total_size += (float)$row['SizeMB'];
    }
}

$mysqlRunning = ($conn && @mysqli_ping($conn));
$mysqlVersion = ($conn ? (string)mysqli_get_server_info($conn) : 'Unavailable');
$mysqlServiceName = itm_system_status_is_windows() ? 'mysql / MariaDB (Windows service)' : 'mysqld';
$mysqlDisplayName = 'MySQL Server (active PHP connection)';
?>
<div class="metrics-grid">
    <div class="metric-card">
        <h3>MySQL Service</h3>
        <div class="text-center mb-3">
            <span class="status-badge <?php echo $mysqlRunning ? 'status-running' : 'status-stopped'; ?>">
                <?php echo $mysqlRunning ? 'Running' : 'Stopped'; ?>
            </span>
        </div>
        <table class="info-table">
            <tr><td>Service Name</td><td><?php echo sanitize($mysqlServiceName); ?></td></tr>
            <tr><td>Display Name</td><td><?php echo sanitize($mysqlDisplayName); ?></td></tr>
            <tr><td>Version</td><td><?php echo sanitize($mysqlVersion); ?></td></tr>
        </table>
        <?php if (itm_system_status_is_windows()): ?>
            <p class="metric-label" style="margin-top:12px;">Windows SCM details (optional): run <code>php scripts/test_mysql_status.php</code> when <code>shell_exec</code> is enabled.</p>
        <?php endif; ?>
    </div>

    <div class="metric-card">
        <h3>Storage Summary</h3>
        <div class="text-center" style="padding: 20px 0;">
            <div class="metric-value"><?php echo number_format($total_size, 2); ?> MB</div>
            <div class="metric-label">Total Data Size</div>
        </div>
        <div class="text-center" style="padding: 10px 0;">
            <div class="metric-value"><?php echo count($db_metrics); ?></div>
            <div class="metric-label">Total Databases</div>
        </div>
    </div>

    <div class="metric-card" style="grid-column: span 2;">
        <h3>Database Metrics</h3>
        <div class="audit-table-wrap">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th style="text-align: right;">Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($db_metrics)): ?>
                        <tr><td colspan="2">No databases found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($db_metrics as $db): ?>
                            <tr>
                                <td><?php echo sanitize($db['Database']); ?></td>
                                <td style="text-align: right;"><?php echo number_format((float)$db['SizeMB'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
