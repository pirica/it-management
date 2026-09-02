<?php
/** Staging queue tab (shared partial). */
$ndStagingFormBase = $ndStagingFormBase ?? $ndStagingTabUrl;
?>
<div class="card" style="margin-bottom:16px;padding:12px;">
    <form method="GET" action="<?php echo sanitize($ndStagingFormBase); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <?php if (strpos($ndStagingFormBase, 'tab=') === false): ?>
            <input type="hidden" name="tab" value="staging">
        <?php endif; ?>
        <div class="form-group" style="margin:0;">
            <label for="nd-status">Status</label>
            <select id="nd-status" name="status">
                <?php foreach (['pending', 'promoted', 'dismissed', 'all'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $stagingStatus === $st ? 'selected' : ''; ?>><?php echo sanitize($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label for="nd-profile-filter">Profile</label>
            <select id="nd-profile-filter" name="profile_id">
                <option value="0">All profiles</option>
                <?php foreach ($profiles as $profile): ?>
                    <option value="<?php echo (int)$profile['id']; ?>" <?php echo $profileFilter === (int)$profile['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize((string)$profile['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="card" style="overflow:auto;">
    <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
        <thead>
        <tr>
            <th>IP</th>
            <th>Hostname guess</th>
            <th>Profile</th>
            <th>Reachability</th>
            <th>Status</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($stagingRows === []): ?>
            <tr><td colspan="6" style="text-align:center;">No staging rows.</td></tr>
        <?php else: foreach ($stagingRows as $stagingRow): ?>
            <?php
            $probe = json_decode((string)($stagingRow['probe_json'] ?? '{}'), true);
            if (!is_array($probe)) {
                $probe = [];
            }
            $portUsed = $probe['port_used'] ?? '';
            $responseMs = $probe['response_ms'] ?? '';
            $httpServer = (string)($probe['http_server'] ?? '');
            $snmpName = (string)($probe['snmp_sysname'] ?? '');
            $matchedEq = (int)($probe['equipment_id'] ?? 0);
            $reachLabel = $portUsed !== '' ? 'TCP ' . $portUsed . ' (' . $responseMs . ' ms)' : '—';
            if ($httpServer !== '') {
                $reachLabel .= '; HTTP ' . $httpServer;
            }
            if ($snmpName !== '') {
                $reachLabel .= '; SNMP ' . $snmpName;
            }
            $rowStatus = (string)($stagingRow['status'] ?? '');
            $stagingId = (int)($stagingRow['id'] ?? 0);
            $equipmentViewPrefix = $ndEquipmentViewPrefix ?? '../equipment/';
            ?>
            <tr>
                <td><code><?php echo sanitize((string)($stagingRow['ip_address'] ?? '')); ?></code></td>
                <td><?php echo sanitize((string)($stagingRow['hostname_guess'] ?? '')); ?></td>
                <td><?php echo sanitize((string)($stagingRow['profile_name'] ?? '')); ?></td>
                <td style="font-size:13px;color:#57606a;"><?php echo sanitize($reachLabel); ?></td>
                <td><?php echo sanitize($rowStatus); ?></td>
                <td class="itm-actions-cell" data-itm-actions-origin="1">
                    <?php if ($rowStatus === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-primary nd-promote-btn" data-staging-id="<?php echo $stagingId; ?>" title="Promote to equipment">➕</button>
                        <button type="button" class="btn btn-sm nd-link-btn" data-staging-id="<?php echo $stagingId; ?>" data-matched-eq="<?php echo $matchedEq; ?>" title="Link existing">🔗</button>
                        <button type="button" class="btn btn-sm nd-dismiss-btn" data-staging-id="<?php echo $stagingId; ?>" title="Dismiss">🗑️</button>
                    <?php elseif ($rowStatus === 'promoted' && (int)($stagingRow['promoted_equipment_id'] ?? 0) > 0): ?>
                        <a class="btn btn-sm" href="<?php echo sanitize($equipmentViewPrefix); ?>view.php?id=<?php echo (int)$stagingRow['promoted_equipment_id']; ?>" title="View equipment">🔎</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($stagingPages > 1): ?>
<div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
    <?php
    $baseQs = 'tab=staging&status=' . urlencode($stagingStatus) . '&profile_id=' . $profileFilter;
    $paginationPrefix = $ndPaginationPrefix ?? '?';
    if ($page > 1): ?>
        <a class="btn btn-sm" href="<?php echo sanitize($paginationPrefix . $baseQs . '&page=1'); ?>" title="First page">⏮️</a>
        <a class="btn btn-sm" href="<?php echo sanitize($paginationPrefix . $baseQs . '&page=' . ($page - 1)); ?>" title="Previous page">◀️</a>
    <?php endif; ?>
    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $stagingPages; ?></span>
    <?php if ($page < $stagingPages): ?>
        <a class="btn btn-sm" href="<?php echo sanitize($paginationPrefix . $baseQs . '&page=' . ($page + 1)); ?>" title="Next page">▶️</a>
        <a class="btn btn-sm" href="<?php echo sanitize($paginationPrefix . $baseQs . '&page=' . $stagingPages); ?>" title="Last page">⏭️</a>
    <?php endif; ?>
</div>
<?php endif; ?>
