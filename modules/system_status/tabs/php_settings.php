<?php
/**
 * PHP Settings Tab
 *
 * Displays detailed PHP configuration and enabled extensions.
 */
?>
<div class="metrics-grid">
    <!-- PHP Version & Core Info -->
    <div class="metric-card">
        <h3>PHP Core</h3>
        <div id="php-core-loader" class="text-center">Loading...</div>
        <div id="php-core-content" style="display:none;">
            <table class="info-table">
                <tr><td>Version</td><td id="php_version_str"></td></tr>
                <tr><td>Configuration File</td><td id="php_ini_path" style="word-break:break-all; font-size: 0.8rem;"></td></tr>
            </table>
        </div>
    </div>

    <!-- PHP INI Limits -->
    <div class="metric-card">
        <h3>Resource Limits</h3>
        <div id="php-limits-loader" class="text-center">Loading...</div>
        <div id="php-limits-content" style="display:none;">
            <table class="info-table">
                <tr><td>memory_limit</td><td id="php_mem_limit"></td></tr>
                <tr><td>upload_max_filesize</td><td id="php_upload_max"></td></tr>
                <tr><td>post_max_size</td><td id="php_post_max"></td></tr>
                <tr><td>max_execution_time</td><td id="php_max_exec"></td></tr>
            </table>
        </div>
    </div>

    <!-- PHP Extensions -->
    <div class="metric-card" style="grid-column: span 2;">
        <h3>Enabled Extensions</h3>
        <div id="php-ext-loader" class="text-center">Loading...</div>
        <div id="php-ext-content" style="display:none; column-count: 3; gap: 20px;">
            <ul id="php_extensions_list" style="margin:0; padding:0; list-style:none; font-size: 0.85rem;">
                <!-- Extensions will be populated here -->
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '../../scripts/system_status_api.php?action=';

    fetch(apiBase + 'php_version')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('php_version_str').textContent = res.data.version;
                document.getElementById('php_ini_path').textContent = res.data.ini_path;
                document.getElementById('php-core-loader').style.display = 'none';
                document.getElementById('php-core-content').style.display = 'block';
            }
        });

    fetch(apiBase + 'php_ini_values')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const d = res.data;
                document.getElementById('php_mem_limit').textContent = d.memory_limit;
                document.getElementById('php_upload_max').textContent = d.upload_max_filesize;
                document.getElementById('php_post_max').textContent = d.post_max_size;
                document.getElementById('php_max_exec').textContent = d.max_execution_time + 's';
                document.getElementById('php-limits-loader').style.display = 'none';
                document.getElementById('php-limits-content').style.display = 'block';
            }
        });

    fetch(apiBase + 'php_extensions')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const list = document.getElementById('php_extensions_list');
                const extensions = [].concat(res.data || []);
                extensions.sort().forEach(ext => {
                    const li = document.createElement('li');
                    li.textContent = '✅ ' + ext;
                    li.style.padding = '2px 0';
                    list.appendChild(li);
                });
                document.getElementById('php-ext-loader').style.display = 'none';
                document.getElementById('php-ext-content').style.display = 'block';
            }
        });
});
</script>
