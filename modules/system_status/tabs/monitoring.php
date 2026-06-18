<?php
/**
 * Monitoring Tab
 *
 * Renders cached hardware metrics and Sub Storage from system_status.payload_json.
 */

if (!is_array($ssPayload ?? null)) {
    echo '<div class="alert alert-warning">No cached monitoring data. Click <strong>Refresh</strong> to collect metrics.</div>';
    return;
}

$systemInfoPayload = $ssPayload['system_info'] ?? null;
$cpuUsagePayload = $ssPayload['cpu_usage'] ?? null;
$storageReport = is_array($ssPayload['storage_report'] ?? null) ? $ssPayload['storage_report'] : [
    'sections' => [],
    'total_bytes' => 0,
    'total_files' => 0,
];
$systemInfo = (is_array($systemInfoPayload) && ($systemInfoPayload['status'] ?? '') === 'success')
    ? ($systemInfoPayload['data'] ?? [])
    : null;
$cpuLoad = (is_array($cpuUsagePayload) && ($cpuUsagePayload['status'] ?? '') === 'success')
    ? (float)($cpuUsagePayload['data']['cpu_load'] ?? 0)
    : null;
?>
<div class="metrics-grid">
    <div class="metric-card" style="grid-column: span 2;">
        <h3>System Overview</h3>
        <?php if ($systemInfo === null): ?>
            <div class="text-center" style="color:#a52727;">
                <?php echo sanitize((string)($systemInfoPayload['message'] ?? 'Cached system info unavailable.')); ?>
            </div>
        <?php else: ?>
            <div id="system-info-content">
                <table class="info-table">
                    <tr><td>OS Version</td><td id="os_version"><?php echo sanitize((string)($systemInfo['os_version'] ?? '')); ?></td></tr>
                    <tr><td>Hostname</td><td id="hostname"><?php echo sanitize((string)($systemInfo['hostname'] ?? '')); ?></td></tr>
                    <tr><td>Uptime</td><td id="uptime_str"><?php echo sanitize((string)($systemInfo['uptime'] ?? '')); ?></td></tr>
                    <tr><td>CPU</td><td id="cpu_model"><?php echo sanitize((string)($systemInfo['cpu_model'] ?? '')); ?></td></tr>
                    <tr><td>Cores / Threads</td><td><span id="cpu_cores"><?php echo sanitize((string)($systemInfo['cpu_cores'] ?? '')); ?></span> / <span id="cpu_threads"><?php echo sanitize((string)($systemInfo['cpu_threads'] ?? '')); ?></span></td></tr>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="metric-card">
        <h3>CPU Usage</h3>
        <div class="gauge-container">
            <canvas id="cpuChart"></canvas>
        </div>
        <div class="text-center mt-2">
            <span class="metric-value" id="cpu_load_val"><?php echo $cpuLoad !== null ? sanitize((string)$cpuLoad) : 'N/A'; ?></span><span class="metric-value">%</span>
        </div>
    </div>

    <div class="metric-card">
        <h3>RAM Usage</h3>
        <div class="gauge-container">
            <canvas id="ramChart"></canvas>
        </div>
        <div class="text-center mt-2">
            <span id="ram_used_gb">0</span> / <span id="ram_total_gb">0</span> GB
        </div>
    </div>

    <div class="metric-card" style="grid-column: span 3;">
        <h3>Disk Usage</h3>
        <div id="disk-usage-content" class="metrics-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
            <?php if ($systemInfo !== null): ?>
                <?php foreach (($systemInfo['disks'] ?? []) as $disk): ?>
                    <?php
                        $diskSize = (int)($disk['Size'] ?? 0);
                        $diskFree = (int)($disk['FreeSpace'] ?? 0);
                        if ($diskSize <= 0) {
                            continue;
                        }
                        $diskTotalGb = $diskSize / 1073741824;
                        $diskFreeGb = $diskFree / 1073741824;
                        $diskUsedGb = max(0, $diskTotalGb - $diskFreeGb);
                        $diskPercent = $diskTotalGb > 0 ? round(($diskUsedGb / $diskTotalGb) * 100, 1) : 0;
                    ?>
                    <div class="metric-card text-center">
                        <div class="metric-label">Drive <?php echo sanitize((string)($disk['DeviceID'] ?? '')); ?></div>
                        <div class="metric-value"><?php echo sanitize((string)$diskPercent); ?>%</div>
                        <div class="progress-bar-container" style="height:8px; background:#f0f3f6; border-radius:4px; margin:8px 0;">
                            <div style="width:<?php echo (float)$diskPercent; ?>%; height:100%; background:#17a2b8; border-radius:4px;"></div>
                        </div>
                        <div class="metric-label"><?php echo sanitize(number_format($diskUsedGb, 1)); ?>G / <?php echo sanitize(number_format($diskTotalGb, 1)); ?>G</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="metric-card" style="margin-top: 16px;">
    <h3>Sub Storage — Explorer and upload trees</h3>
    <p class="metric-label" style="margin-top:0;">On-disk usage for Explorer segments by company, plus shared upload directories.</p>
    <?php foreach ($storageReport['sections'] as $section): ?>
        <?php itm_system_status_render_storage_node($section); ?>
    <?php endforeach; ?>
    <div class="ss-storage-total">
        Total: <?php echo sanitize(itm_system_status_format_bytes((int)$storageReport['total_bytes'])); ?>
        · <?php echo number_format((int)$storageReport['total_files']); ?> files
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cpuLoad = <?php echo json_encode($cpuLoad, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const systemInfo = <?php echo json_encode($systemInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const ctxCpu = document.getElementById('cpuChart').getContext('2d');
    const cpuChart = new Chart(ctxCpu, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [0, 100],
                backgroundColor: ['#28a745', '#f0f3f6'],
                borderWidth: 0
            }]
        },
        options: {
            circumference: 180,
            rotation: -90,
            cutout: '80%',
            plugins: { tooltip: { enabled: false } }
        }
    });

    const ctxRam = document.getElementById('ramChart').getContext('2d');
    const ramChart = new Chart(ctxRam, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [0, 100],
                backgroundColor: ['#007bff', '#f0f3f6'],
                borderWidth: 0
            }]
        },
        options: {
            circumference: 180,
            rotation: -90,
            cutout: '80%',
            plugins: { tooltip: { enabled: false } }
        }
    });

    if (cpuLoad !== null) {
        cpuChart.data.datasets[0].data = [cpuLoad, 100 - cpuLoad];
        cpuChart.update();
    }

    if (systemInfo && systemInfo.ram_total > 0) {
        const totalGb = (systemInfo.ram_total / 1073741824).toFixed(2);
        const usedGb = (systemInfo.ram_used / 1073741824).toFixed(2);
        const usedPercent = ((systemInfo.ram_used / systemInfo.ram_total) * 100).toFixed(1);

        document.getElementById('ram_used_gb').textContent = usedGb;
        document.getElementById('ram_total_gb').textContent = totalGb;

        ramChart.data.datasets[0].data = [usedPercent, 100 - usedPercent];
        ramChart.update();
    }
});
</script>
