<?php
/**
 * Subnet detail: IP list and /24 bulk-generate (included from view screen only).
 */
if (($crud_action ?? '') !== 'view') {
    return;
}

$itmSubnetViewId = (int)($data['id'] ?? 0);
$itmSubnetPrefixLength = (int)($data['prefix_length'] ?? 0);
$itmSubnetCanBulkGenerate = ($itmSubnetPrefixLength === 24);
$itmSubnetAddressRows = [];
$itmSubnetAddressTotal = 0;

if ($itmSubnetViewId > 0 && function_exists('itm_ipam_fetch_subnet_addresses')) {
    $itmSubnetAddressRows = itm_ipam_fetch_subnet_addresses($conn, (int)$company_id, $itmSubnetViewId, 300);
    $itmSubnetAddressTotal = itm_ipam_count_subnet_addresses($conn, (int)$company_id, $itmSubnetViewId);
}
?>
<div class="card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;">IP addresses in this subnet</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-sm" href="../ip_addresses/index.php?subnet_id=<?php echo (int)$itmSubnetViewId; ?>">Open full IP list</a>
            <?php if ($itmSubnetCanBulkGenerate): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Generate all host IPs for this /24? Existing IPs are kept.');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="subnet_id" value="<?php echo (int)$itmSubnetViewId; ?>">
                    <button type="submit" name="generate_subnet_ips" value="1" class="btn btn-sm btn-primary">Generate /24 host IPs</button>
                </form>
            <?php endif; ?>
        </div>
        </div>
    <p style="margin:0 0 12px;color:#57606a;">
        <?php echo (int)$itmSubnetAddressTotal; ?> total
        <?php if ($itmSubnetAddressTotal > count($itmSubnetAddressRows)): ?>
            (showing first <?php echo count($itmSubnetAddressRows); ?>)
        <?php endif; ?>
        <?php if (!$itmSubnetCanBulkGenerate): ?>
            — bulk generate is available for /24 subnets only.
        <?php endif; ?>
    </p>
    <div style="overflow:auto;">
        <table>
            <thead>
            <tr>
                <th>IP</th>
                <th>Status</th>
                <th>Equipment</th>
                <th>Hostname</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($itmSubnetAddressRows): ?>
                <?php foreach ($itmSubnetAddressRows as $itmSubnetIpRow): ?>
                    <tr>
                        <td><?php echo sanitize((string)($itmSubnetIpRow['ip_text'] ?? '')); ?></td>
                        <td><?php echo cr_render_cell_value('ip_addresses', 'status', $itmSubnetIpRow['status'] ?? ''); ?></td>
                        <td>
                            <?php
                                $itmEquipId = (int)($itmSubnetIpRow['equipment_id'] ?? 0);
                                $itmEquipLabel = function_exists('itm_ipam_equipment_label_from_row')
                                    ? itm_ipam_equipment_label_from_row($itmSubnetIpRow)
                                    : '';
                                if ($itmEquipId > 0 && $itmEquipLabel !== ''): ?>
                                    <a href="../equipment/view.php?id=<?php echo $itmEquipId; ?>"><?php echo sanitize($itmEquipLabel); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                        </td>
                        <td><?php echo sanitize((string)($itmSubnetIpRow['hostname'] ?? '')); ?></td>
                        <td>
                            <a class="btn btn-sm" href="../ip_addresses/view.php?id=<?php echo (int)($itmSubnetIpRow['id'] ?? 0); ?>">🔎</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No IP addresses yet.<?php if ($itmSubnetCanBulkGenerate): ?> Use Generate /24 host IPs to populate this subnet.<?php endif; ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
