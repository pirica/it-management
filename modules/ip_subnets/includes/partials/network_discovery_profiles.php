<?php
/** Discovery profiles tab (shared partial). */
?>
<?php if (!$isAdmin): ?>
    <div class="alert alert-warning">Only administrators can manage discovery profiles.</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:16px;">
    <h2 style="margin-top:0;font-size:18px;">New profile</h2>
    <?php if ($isAdmin): ?>
    <form method="POST" style="display:grid;gap:12px;max-width:720px;">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="nd_action" value="save_profile">
        <input type="hidden" name="profile_id" value="0">
        <div class="form-group">
            <label for="nd-name">Name</label>
            <input type="text" id="nd-name" name="name" required maxlength="120" placeholder="Nightly office LAN">
        </div>
        <div class="form-group">
            <label for="nd-cron">Schedule cron (minute hour dom month dow)</label>
            <input type="text" id="nd-cron" name="schedule_cron" required placeholder="0 2 * * *" value="0 2 * * *">
        </div>
        <div class="form-group">
            <label>Subnets</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($subnetOptions as $subnetOpt): ?>
                    <label class="itm-checkbox-control" style="margin:0;">
                        <input type="checkbox" name="subnet_ids[]" value="<?php echo (int)$subnetOpt['id']; ?>">
                        <span><?php echo sanitize((string)$subnetOpt['cidr']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="nd-policy">Auto-create policy</label>
            <select id="nd-policy" name="auto_create_policy">
                <option value="review">review — staging queue only</option>
                <option value="none">none — scan only, no staging auto-promote</option>
                <option value="equipment">equipment — auto-create assets on discovery</option>
            </select>
        </div>
        <label class="itm-checkbox-control">
            <input type="checkbox" name="snmp_enabled" value="1">
            <span>SNMP sysName (optional PHP snmp extension)</span>
        </label>
        <label class="itm-checkbox-control">
            <input type="checkbox" name="enabled" value="1" checked>
            <span>Enabled</span>
        </label>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="overflow:auto;">
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Schedule</th>
            <th>Policy</th>
            <th>SNMP</th>
            <th>Enabled</th>
            <th>Last run</th>
            <th>Scan</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($profiles === []): ?>
            <tr><td colspan="8" style="text-align:center;">No profiles yet.</td></tr>
        <?php else: foreach ($profiles as $profile): ?>
            <?php
            $subnetIds = itm_network_discovery_decode_subnet_ids((string)($profile['subnet_ids_json'] ?? '[]'));
            $subnetLabels = [];
            foreach ($subnetOptions as $subnetOpt) {
                if (in_array((int)$subnetOpt['id'], $subnetIds, true)) {
                    $subnetLabels[] = (string)$subnetOpt['cidr'];
                }
            }
            $profileIdRow = (int)($profile['id'] ?? 0);
            $scanInProgress = itm_background_jobs_profile_has_active_scan($conn, $companyId, $profileIdRow);
            ?>
            <tr>
                <td><?php echo sanitize((string)($profile['name'] ?? '')); ?></td>
                <td><code><?php echo sanitize((string)($profile['schedule_cron'] ?? '')); ?></code></td>
                <td><?php echo sanitize((string)($profile['auto_create_policy'] ?? '')); ?></td>
                <td><?php echo (int)($profile['snmp_enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                <td><?php echo (int)($profile['enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                <td><?php echo sanitize(itm_format_cell_scalar_display('last_run_at', $profile['last_run_at'] ?? null)); ?></td>
                <td><?php echo $scanInProgress ? 'In progress' : '—'; ?></td>
                <td class="itm-actions-cell" data-itm-actions-origin="1">
                    <?php if ($isAdmin): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="nd_action" value="run_profile">
                        <input type="hidden" name="profile_id" value="<?php echo $profileIdRow; ?>">
                        <button type="submit" class="btn btn-sm" title="Run scan batch">▶️</button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this profile?');">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="nd_action" value="delete_profile">
                        <input type="hidden" name="profile_id" value="<?php echo $profileIdRow; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                    <small style="display:block;color:#57606a;margin-top:4px;"><?php echo sanitize(implode(', ', $subnetLabels)); ?></small>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
