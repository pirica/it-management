<?php
/**
 * Subnet CIDR calculator summary (included from view screen only).
 */
if (($crud_action ?? '') !== 'view') {
    return;
}

$itmSubnetStats = null;
if (function_exists('itm_ipam_subnet_statistics')) {
    $itmSubnetStats = itm_ipam_subnet_statistics(
        (string)($data['network_ip'] ?? ''),
        (int)($data['prefix_length'] ?? 0)
    );
}
if (!$itmSubnetStats) {
    return;
}
?>
<div class="card" style="margin-top:20px;">
    <h2 style="margin:0 0 12px;">Subnet summary</h2>
    <table>
        <tbody>
        <tr>
            <th style="width:200px;">CIDR</th>
            <td><?php echo sanitize((string)$itmSubnetStats['cidr']); ?></td>
        </tr>
        <tr>
            <th>Total IPs</th>
            <td><?php echo number_format((int)$itmSubnetStats['total_ips']); ?></td>
        </tr>
        <tr>
            <th>Usable IPs</th>
            <td><?php echo number_format((int)$itmSubnetStats['usable_ips']); ?></td>
        </tr>
        <tr>
            <th>Network</th>
            <td><?php echo sanitize((string)$itmSubnetStats['network']); ?></td>
        </tr>
        <tr>
            <th>Broadcast</th>
            <td><?php echo sanitize((string)$itmSubnetStats['broadcast']); ?></td>
        </tr>
        <tr>
            <th>Usable range</th>
            <td><?php echo sanitize((string)$itmSubnetStats['usable_range']); ?></td>
        </tr>
        <?php if ((int)$itmSubnetStats['usable_ips'] > (int)$itmSubnetStats['bulk_generate_cap']): ?>
            <tr>
                <th>Bulk generate</th>
                <td>
                    Up to <?php echo number_format((int)$itmSubnetStats['bulk_generate_cap']); ?> IP records per click
                    (<?php echo sanitize((string)$itmSubnetStats['first_usable']); ?> onward). Use multiple runs or smaller child subnets to cover all <?php echo number_format((int)$itmSubnetStats['usable_ips']); ?> usable addresses.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
