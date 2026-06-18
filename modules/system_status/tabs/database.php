<?php
/**
 * Database Tab
 *
 * Renders cached MySQL metrics from system_status.payload_json.
 */

require_once dirname(__DIR__, 3) . '/includes/itm_system_status_native.php';

if (!is_array($ssPayload ?? null)) {
    echo '<div class="alert alert-warning">No cached database metrics. Click <strong>Refresh</strong> to collect metrics.</div>';
    return;
}

$activeDatabase = (string)($ssPayload['active_database'] ?? '');
$dbReport = is_array($ssPayload['db_report'] ?? null) ? $ssPayload['db_report'] : [
    'tables' => [],
    'total_rows' => 0,
    'total_size_mb' => 0.0,
    'table_count' => 0,
    'database' => $activeDatabase,
];
$mysqlRunning = !empty($ssPayload['mysql_running']);
$mysqlVersion = (string)($ssPayload['mysql_version'] ?? 'Unavailable');
$mysqlServiceName = (string)($ssPayload['mysql_service_name'] ?? 'mysqld');
$mysqlDisplayName = (string)($ssPayload['mysql_display_name'] ?? 'MySQL Server (active PHP connection)');
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
            <p class="metric-label ss-note-spaced">Windows SCM details (optional): run <code>php scripts/test_mysql_status.php</code> when <code>shell_exec</code> is enabled.</p>
        <?php endif; ?>
    </div>

    <div class="metric-card">
        <h3>Storage Summary</h3>
        <div class="text-center ss-metric-block-lg">
            <div class="metric-value"><?php echo number_format((float)($dbReport['total_size_mb'] ?? 0), 2); ?> MB</div>
            <div class="metric-label">Total Data Size (<?php echo sanitize($activeDatabase); ?>)</div>
        </div>
        <div class="text-center ss-metric-block">
            <div class="metric-value"><?php echo number_format((int)($dbReport['table_count'] ?? 0)); ?></div>
            <div class="metric-label">Tables</div>
        </div>
        <div class="text-center ss-metric-block">
            <div class="metric-value"><?php echo number_format((int)($dbReport['total_rows'] ?? 0)); ?></div>
            <div class="metric-label">Approx. Total Rows</div>
        </div>
    </div>

    <div class="metric-card ss-metric-span-full">
        <h3>Database Metrics — <?php echo sanitize($activeDatabase); ?></h3>
        <div class="audit-table-wrap">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th class="ss-table-num">Rows</th>
                        <th class="ss-table-num">Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dbReport['tables'])): ?>
                        <tr><td colspan="3">No tables found for this database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dbReport['tables'] as $table): ?>
                            <tr>
                                <td><?php echo sanitize((string)($table['name'] ?? '')); ?></td>
                                <td class="ss-table-num"><?php echo number_format((int)($table['rows'] ?? 0)); ?></td>
                                <td class="ss-table-num"><?php echo number_format((float)($table['size_mb'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="ss-table-num"><strong><?php echo number_format((int)($dbReport['total_rows'] ?? 0)); ?></strong></td>
                            <td class="ss-table-num"><strong><?php echo number_format((float)($dbReport['total_size_mb'] ?? 0), 2); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="metric-label ss-note-spaced">Row counts come from <code>information_schema.TABLES.table_rows</code> (approximate for InnoDB).</p>
    </div>
</div>
