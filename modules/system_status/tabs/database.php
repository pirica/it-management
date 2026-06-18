<?php
/**
 * Database Tab
 *
 * MySQL metrics from the active mysqli connection (server-rendered).
 */

require_once dirname(__DIR__, 3) . '/includes/itm_system_status_native.php';
require_once dirname(__DIR__, 3) . '/includes/itm_system_status_storage.php';

$activeDatabase = defined('DB_NAME') ? (string)DB_NAME : '';
$dbReport = itm_system_status_build_database_table_report($conn, $activeDatabase);

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
            <tr><td>Active Database</td><td><?php echo sanitize($activeDatabase); ?></td></tr>
            <tr><td>Version</td><td><?php echo sanitize($mysqlVersion); ?></td></tr>
        </table>
        <?php if (itm_system_status_is_windows()): ?>
            <p class="metric-label" style="margin-top:12px;">Windows SCM details (optional): run <code>php scripts/test_mysql_status.php</code> when <code>shell_exec</code> is enabled.</p>
        <?php endif; ?>
    </div>

    <div class="metric-card">
        <h3>Storage Summary</h3>
        <div class="text-center" style="padding: 20px 0;">
            <div class="metric-value"><?php echo number_format((float)$dbReport['total_size_mb'], 2); ?> MB</div>
            <div class="metric-label">Total Data Size (<?php echo sanitize($activeDatabase); ?>)</div>
        </div>
        <div class="text-center" style="padding: 10px 0;">
            <div class="metric-value"><?php echo number_format((int)$dbReport['table_count']); ?></div>
            <div class="metric-label">Tables</div>
        </div>
        <div class="text-center" style="padding: 10px 0;">
            <div class="metric-value"><?php echo number_format((int)$dbReport['total_rows']); ?></div>
            <div class="metric-label">Approx. Total Rows</div>
        </div>
    </div>

    <div class="metric-card" style="grid-column: 1 / -1;">
        <h3>Database Metrics — <?php echo sanitize($activeDatabase); ?></h3>
        <div class="audit-table-wrap">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th style="text-align: right;">Rows</th>
                        <th style="text-align: right;">Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dbReport['tables'])): ?>
                        <tr><td colspan="3">No tables found for this database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dbReport['tables'] as $table): ?>
                            <tr>
                                <td><?php echo sanitize($table['name']); ?></td>
                                <td style="text-align: right;"><?php echo number_format((int)$table['rows']); ?></td>
                                <td style="text-align: right;"><?php echo number_format((float)$table['size_mb'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td style="text-align: right;"><strong><?php echo number_format((int)$dbReport['total_rows']); ?></strong></td>
                            <td style="text-align: right;"><strong><?php echo number_format((float)$dbReport['total_size_mb'], 2); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="metric-label" style="margin-top:12px;">Row counts come from <code>information_schema.TABLES.table_rows</code> (approximate for InnoDB).</p>
    </div>
</div>
