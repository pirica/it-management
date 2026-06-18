<?php
/**
 * PHP Settings Tab
 *
 * Renders the active Apache PHP runtime directly (no PowerShell / AJAX).
 */

$phpIniPath = php_ini_loaded_file() ?: '';
$phpExtensions = get_loaded_extensions();
sort($phpExtensions);
$phpIniValues = [
    'memory_limit' => (string)ini_get('memory_limit'),
    'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
    'post_max_size' => (string)ini_get('post_max_size'),
    'max_execution_time' => (string)ini_get('max_execution_time'),
];
$phpInfoUrl = '../../scripts/system_status_phpinfo.php';
?>
<div class="metrics-grid">
    <div class="metric-card">
        <h3>PHP Core</h3>
        <table class="info-table">
            <tr><td>Version</td><td><?php echo sanitize('PHP ' . PHP_VERSION); ?></td></tr>
            <tr><td>SAPI</td><td><?php echo sanitize(php_sapi_name()); ?></td></tr>
            <tr><td>Binary</td><td style="word-break:break-all; font-size: 0.8rem;"><?php echo sanitize(PHP_BINARY); ?></td></tr>
            <tr><td>Configuration File</td><td style="word-break:break-all; font-size: 0.8rem;"><?php echo sanitize($phpIniPath !== '' ? $phpIniPath : 'None'); ?></td></tr>
        </table>
        <p style="margin-top:12px;">
            <a class="btn btn-sm" href="<?php echo sanitize($phpInfoUrl); ?>" target="_blank" rel="noopener">Open full phpinfo()</a>
        </p>
    </div>

    <div class="metric-card">
        <h3>Resource Limits</h3>
        <table class="info-table">
            <tr><td>memory_limit</td><td><?php echo sanitize($phpIniValues['memory_limit']); ?></td></tr>
            <tr><td>upload_max_filesize</td><td><?php echo sanitize($phpIniValues['upload_max_filesize']); ?></td></tr>
            <tr><td>post_max_size</td><td><?php echo sanitize($phpIniValues['post_max_size']); ?></td></tr>
            <tr><td>max_execution_time</td><td><?php echo sanitize($phpIniValues['max_execution_time']); ?>s</td></tr>
        </table>
    </div>

    <div class="metric-card" style="grid-column: span 2;">
        <h3>Enabled Extensions (<?php echo count($phpExtensions); ?>)</h3>
        <div style="column-count: 3; gap: 20px;">
            <ul style="margin:0; padding:0; list-style:none; font-size: 0.85rem;">
                <?php foreach ($phpExtensions as $extension): ?>
                    <li style="padding: 2px 0;">✅ <?php echo sanitize($extension); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
