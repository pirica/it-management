<?php
/**
 * Subnet detail: IP list and bulk-generate (included from view screen only).
 */
if (($crud_action ?? '') !== 'view') {
    return;
}

$itmSubnetViewId = (int)($data['id'] ?? 0);
$itmSubnetPrefixLength = (int)($data['prefix_length'] ?? 0);
$itmSubnetBulkMaxHosts = function_exists('itm_ipam_subnet_bulk_generate_max_hosts')
    ? itm_ipam_subnet_bulk_generate_max_hosts($itmSubnetPrefixLength)
    : 0;
$itmSubnetCanBulkGenerate = $itmSubnetBulkMaxHosts > 0;
$itmSubnetBulkHostTotal = ($itmSubnetPrefixLength >= 0 && $itmSubnetPrefixLength <= 30)
    ? max(0, (int)(2 ** (32 - $itmSubnetPrefixLength)) - 2)
    : 0;
$itmSubnetBulkIsCapped = $itmSubnetCanBulkGenerate && $itmSubnetBulkHostTotal > $itmSubnetBulkMaxHosts;
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
                <?php
                    $itmBulkConfirm = $itmSubnetBulkIsCapped
                        ? 'Generate up to ' . (int)$itmSubnetBulkMaxHosts . ' host IPs (first usable addresses in this subnet)? Existing IPs are kept.'
                        : 'Generate all host IPs for this subnet? Existing IPs are kept.';
                    $itmBulkButtonLabel = $itmSubnetBulkIsCapped
                        ? 'Generate host IPs (up to ' . (int)$itmSubnetBulkMaxHosts . ')'
                        : 'Generate host IPs';
                ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm(<?php echo json_encode($itmBulkConfirm); ?>);">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="subnet_id" value="<?php echo (int)$itmSubnetViewId; ?>">
                    <button type="submit" name="generate_subnet_ips" value="1" class="btn btn-sm btn-primary"><?php echo sanitize($itmBulkButtonLabel); ?></button>
                </form>
            <?php endif; ?>
        </div>
        </div>
    <p style="margin:0 0 12px;color:#57606a;">
        <strong><?php echo number_format((int)$itmSubnetAddressTotal); ?></strong> IP record(s) stored in this subnet
        <?php if ($itmSubnetAddressTotal > count($itmSubnetAddressRows)): ?>
            (preview shows first <?php echo count($itmSubnetAddressRows); ?>)
        <?php endif; ?>
        <?php if ($itmSubnetBulkIsCapped && $itmSubnetBulkHostTotal > 0): ?>
            — CIDR has <?php echo number_format($itmSubnetBulkHostTotal); ?> usable addresses; each generate adds up to <?php echo number_format((int)$itmSubnetBulkMaxHosts); ?> rows starting at the network + 1.
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
                                $itmEquipLabel = function_exists('itm_ipam_equipment_name_label_from_row')
                                    ? itm_ipam_equipment_name_label_from_row($itmSubnetIpRow)
                                    : (function_exists('itm_ipam_equipment_label_from_row') ? itm_ipam_equipment_label_from_row($itmSubnetIpRow) : '');
                                $itmHostnameDisplay = function_exists('itm_ipam_hostname_display_from_row')
                                    ? itm_ipam_hostname_display_from_row($itmSubnetIpRow)
                                    : trim((string)($itmSubnetIpRow['hostname'] ?? ''));
                                if ($itmEquipId > 0 && $itmEquipLabel !== ''): ?>
                                    <a href="../equipment/view.php?id=<?php echo $itmEquipId; ?>"><?php echo sanitize($itmEquipLabel); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                        </td>
                        <td><?php echo $itmHostnameDisplay !== '' ? sanitize($itmHostnameDisplay) : '—'; ?></td>
                        <td>
                            <a class="btn btn-sm" href="../ip_addresses/view.php?id=<?php echo (int)($itmSubnetIpRow['id'] ?? 0); ?>">🔎</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No IP addresses yet.<?php if ($itmSubnetCanBulkGenerate): ?> Use <?php echo sanitize($itmSubnetBulkIsCapped ? 'Generate host IPs (up to ' . (int)$itmSubnetBulkMaxHosts . ')' : 'Generate host IPs'); ?> to populate this subnet.<?php endif; ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
