<?php
/**
 * Database Tab
 *
 * Displays MySQL service status and database metrics using PowerShell and PHP.
 */

// Use existing PHP connection for DB metrics
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
        $total_size += $row['SizeMB'];
    }
}
?>
<div class="metrics-grid">
    <!-- MySQL Service Status -->
    <div class="metric-card">
        <h3>MySQL Service</h3>
        <div id="mysql-status-loader" class="text-center">Loading...</div>
        <div id="mysql-status-content" style="display:none;">
            <div class="text-center mb-3">
                <span id="mysql_badge" class="status-badge">Unknown</span>
            </div>
            <table class="info-table">
                <tr><td>Service Name</td><td id="mysql_service_name"></td></tr>
                <tr><td>Display Name</td><td id="mysql_display_name"></td></tr>
                <tr><td>Version</td><td id="mysql_version_str"></td></tr>
            </table>
        </div>
    </div>

    <!-- DB Storage Summary -->
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

    <!-- Database Sizes (PHP) -->
    <div class="metric-card">
        <h3>Database Metrics (PHP)</h3>
        <div class="audit-table-wrap">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th style="text-align: right;">Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($db_metrics, 0, 10) as $db): ?>
                        <tr>
                            <td><?php echo sanitize($db['Database']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($db['SizeMB'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database Metrics (PowerShell) -->
    <div class="metric-card">
        <h3>Database Metrics (PowerShell)</h3>
        <div id="ps-db-loader" class="text-center">Loading...</div>
        <div id="ps-db-content" style="display:none;" class="audit-table-wrap">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Database</th>
                        <th style="text-align: right;">Size (MB)</th>
                    </tr>
                </thead>
                <tbody id="ps_db_body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '../../scripts/system_status_api.php?action=';

    fetch(apiBase + 'mysql_status')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const d = res.data;
                document.getElementById('mysql_service_name').textContent = d.service_name;
                document.getElementById('mysql_display_name').textContent = d.display_name;

                const badge = document.getElementById('mysql_badge');
                badge.textContent = d.status;
                badge.className = 'status-badge ' + (d.status === 'Running' ? 'status-running' : 'status-stopped');

                document.getElementById('mysql-status-loader').style.display = 'none';
                document.getElementById('mysql-status-content').style.display = 'block';
            }
        });

    fetch(apiBase + 'mysql_version')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('mysql_version_str').textContent = res.data.version;
            }
        });

    fetch(apiBase + 'mysql_size')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const body = document.getElementById('ps_db_body');
                const data = [].concat(res.data || []);
                // Skip header row if PowerShell output includes it
                data.forEach(row => {
                    if (typeof row === 'string' && (row.includes('Database') || row.includes('---'))) return;

                    const parts = typeof row === 'string' ? row.trim().split(/\s+/) : [];
                    if (parts.length >= 2) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${parts[0]}</td><td style="text-align: right;">${parts[1]}</td>`;
                        body.appendChild(tr);
                    }
                });
                document.getElementById('ps-db-loader').style.display = 'none';
                document.getElementById('ps-db-content').style.display = 'block';
            }
        });
});
</script>
