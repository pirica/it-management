<?php
/**
 * PHP Settings Tab
 *
 * Renders cached PHP runtime settings from system_status.payload_json.
 */

if (!is_array($ssPayload ?? null)) {
    echo '<div class="alert alert-warning">No cached PHP settings. Click <strong>Refresh</strong> to collect metrics.</div>';
    return;
}

$phpIniPath = (string)($ssPayload['ini_path'] ?? '');
$phpExtensions = is_array($ssPayload['extensions'] ?? null) ? $ssPayload['extensions'] : [];
$phpIniValues = is_array($ssPayload['ini_values'] ?? null) ? $ssPayload['ini_values'] : [];
$phpInfoUrl = '../../scripts/system_status_phpinfo.php';
?>
<div class="metrics-stack">
    <div class="metric-card">
        <h3>PHP Core</h3>
        <table class="info-table">
            <tr><td>Version</td><td><?php echo sanitize((string)($ssPayload['version'] ?? '')); ?></td></tr>
            <tr><td>SAPI</td><td><?php echo sanitize((string)($ssPayload['sapi'] ?? '')); ?></td></tr>
            <tr>
                <td>Binary</td>
                <td><span class="ss-path-value"><?php echo sanitize((string)($ssPayload['binary'] ?? '')); ?></span></td>
            </tr>
            <tr>
                <td>Configuration File</td>
                <td><span class="ss-path-value"><?php echo sanitize($phpIniPath !== '' ? $phpIniPath : 'None'); ?></span></td>
            </tr>
        </table>
        <p style="margin-top:12px;">
            <a class="btn btn-sm" href="<?php echo sanitize($phpInfoUrl); ?>" target="_blank" rel="noopener">Open full phpinfo()</a>
        </p>
    </div>

    <div class="metric-card">
        <h3>Resource Limits</h3>
        <table class="info-table">
            <tr><td>memory_limit</td><td><?php echo sanitize((string)($phpIniValues['memory_limit'] ?? '')); ?></td></tr>
            <tr><td>upload_max_filesize</td><td><?php echo sanitize((string)($phpIniValues['upload_max_filesize'] ?? '')); ?></td></tr>
            <tr><td>post_max_size</td><td><?php echo sanitize((string)($phpIniValues['post_max_size'] ?? '')); ?></td></tr>
            <tr><td>max_execution_time</td><td><?php echo sanitize((string)($phpIniValues['max_execution_time'] ?? '')); ?>s</td></tr>
        </table>
    </div>

    <div class="metric-card">
        <h3>Enabled Extensions (<?php echo count($phpExtensions); ?>)</h3>
        <div class="ss-extensions-list" tabindex="0">
            <ul class="ss-extensions-columns">
                <?php foreach ($phpExtensions as $extension): ?>
                    <li style="padding: 2px 0;">✅ <?php echo sanitize((string)$extension); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
