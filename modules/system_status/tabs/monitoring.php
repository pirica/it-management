<?php
/**
 * Monitoring Tab
 *
 * Displays real-time system metrics using Chart.js and PowerShell data.
 */
?>
<div class="metrics-grid">
    <!-- System Info -->
    <div class="metric-card" style="grid-column: span 2;">
        <h3>System Overview</h3>
        <div id="system-info-loader" class="text-center">Loading...</div>
        <div id="system-info-content" style="display:none;">
            <table class="info-table">
                <tr><td>OS Version</td><td id="os_version"></td></tr>
                <tr><td>Hostname</td><td id="hostname"></td></tr>
                <tr><td>Uptime</td><td id="uptime_str"></td></tr>
                <tr><td>CPU</td><td id="cpu_model"></td></tr>
                <tr><td>Cores / Threads</td><td><span id="cpu_cores"></span> / <span id="cpu_threads"></span></td></tr>
            </table>
        </div>
    </div>

    <!-- CPU Usage -->
    <div class="metric-card">
        <h3>CPU Usage</h3>
        <div class="gauge-container">
            <canvas id="cpuChart"></canvas>
        </div>
        <div class="text-center mt-2">
            <span class="metric-value" id="cpu_load_val">0</span><span class="metric-value">%</span>
        </div>
    </div>

    <!-- RAM Usage -->
    <div class="metric-card">
        <h3>RAM Usage</h3>
        <div class="gauge-container">
            <canvas id="ramChart"></canvas>
        </div>
        <div class="text-center mt-2">
            <span id="ram_used_gb">0</span> / <span id="ram_total_gb">0</span> GB
        </div>
    </div>

    <!-- Disk Usage -->
    <div class="metric-card" style="grid-column: span 3;">
        <h3>Disk Usage</h3>
        <div id="disk-usage-content" class="metrics-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
            <!-- Dynamic disk cards -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '../../scripts/system_status_api.php?action=';

    // Initialize Charts
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

    // Fetch Data
    fetch(apiBase + 'system_info')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const d = res.data;
                document.getElementById('os_version').textContent = d.os_version;
                document.getElementById('hostname').textContent = d.hostname;
                document.getElementById('uptime_str').textContent = d.uptime;
                document.getElementById('cpu_model').textContent = d.cpu_model;
                document.getElementById('cpu_cores').textContent = d.cpu_cores;
                document.getElementById('cpu_threads').textContent = d.cpu_threads;

                document.getElementById('system-info-loader').style.display = 'none';
                document.getElementById('system-info-content').style.display = 'block';

                // Update RAM Chart
                const totalGb = (d.ram_total / 1073741824).toFixed(2);
                const usedGb = (d.ram_used / 1073741824).toFixed(2);
                const usedPercent = ((d.ram_used / d.ram_total) * 100).toFixed(1);

                document.getElementById('ram_used_gb').textContent = usedGb;
                document.getElementById('ram_total_gb').textContent = totalGb;

                ramChart.data.datasets[0].data = [usedPercent, 100 - usedPercent];
                ramChart.update();

                // Disks
                const diskContainer = document.getElementById('disk-usage-content');
                [].concat(d.disks || []).forEach(disk => {
                    const diskTotal = (disk.Size / 1073741824).toFixed(1);
                    const diskFree = (disk.FreeSpace / 1073741824).toFixed(1);
                    const diskUsed = (diskTotal - diskFree).toFixed(1);
                    const diskPercent = ((diskUsed / diskTotal) * 100).toFixed(1);

                    const diskCard = document.createElement('div');
                    diskCard.className = 'metric-card text-center';
                    diskCard.innerHTML = `
                        <div class="metric-label">Drive ${disk.DeviceID}</div>
                        <div class="metric-value">${diskPercent}%</div>
                        <div class="progress-bar-container" style="height:8px; background:#f0f3f6; border-radius:4px; margin:8px 0;">
                            <div style="width:${diskPercent}%; height:100%; background:#17a2b8; border-radius:4px;"></div>
                        </div>
                        <div class="metric-label">${diskUsed}G / ${diskTotal}G</div>
                    `;
                    diskContainer.appendChild(diskCard);
                });
            }
        });

    fetch(apiBase + 'cpu_usage')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const load = res.data.cpu_load;
                document.getElementById('cpu_load_val').textContent = load;
                cpuChart.data.datasets[0].data = [load, 100 - load];
                cpuChart.update();
            }
        });
});
</script>
