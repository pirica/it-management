<?php
/**
 * Subnet detail: shortcuts to IP Addresses module and bulk-generate (no duplicate IP table).
 */
if (($crud_action ?? '') !== 'view') {
    return;
}

$itmSubnetViewId = (int)($data['id'] ?? 0);
$itmSubnetPrefixLength = (int)($data['prefix_length'] ?? 0);
$itmSubnetBulkUi = function_exists('itm_ipam_subnet_bulk_generate_ui')
    ? itm_ipam_subnet_bulk_generate_ui($itmSubnetPrefixLength)
    : ['can_generate' => false, 'max_hosts' => 0, 'host_total' => 0, 'is_capped' => false, 'confirm_message' => '', 'button_label' => 'Generate host IPs'];
$itmSubnetBulkMaxHosts = (int)($itmSubnetBulkUi['max_hosts'] ?? 0);
$itmSubnetCanBulkGenerate = !empty($itmSubnetBulkUi['can_generate']);
$itmSubnetBulkHostTotal = (int)($itmSubnetBulkUi['host_total'] ?? 0);
$itmSubnetBulkIsCapped = !empty($itmSubnetBulkUi['is_capped']);
$itmSubnetAddressTotal = 0;

if ($itmSubnetViewId > 0 && function_exists('itm_ipam_count_subnet_addresses')) {
    $itmSubnetAddressTotal = itm_ipam_count_subnet_addresses($conn, (int)$company_id, $itmSubnetViewId);
}
?>
<div class="card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 6px;">IP addresses</h2>
            <p style="margin:0;color:#57606a;">
                <?php echo number_format((int)$itmSubnetAddressTotal); ?> IP record(s) in this subnet — manage them in
                <a href="../ip_addresses/index.php?subnet_id=<?php echo (int)$itmSubnetViewId; ?>">IP Addresses</a>.
            </p>
            <?php if ($itmSubnetBulkIsCapped && $itmSubnetBulkHostTotal > 0): ?>
                <p style="margin:6px 0 0;color:#57606a;font-size:13px;">
                    Bulk generate adds up to <?php echo number_format((int)$itmSubnetBulkMaxHosts); ?> host IPs per run
                    (<?php echo number_format($itmSubnetBulkHostTotal); ?> usable in-CIDR).
                </p>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a class="btn btn-sm btn-primary" href="../ip_addresses/index.php?subnet_id=<?php echo (int)$itmSubnetViewId; ?>">Open IP list</a>
            <?php if ($itmSubnetCanBulkGenerate): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm(<?php echo json_encode((string)($itmSubnetBulkUi['confirm_message'] ?? '')); ?>);">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="subnet_id" value="<?php echo (int)$itmSubnetViewId; ?>">
                    <button type="submit" name="generate_subnet_ips" value="1" class="btn btn-sm"><?php echo sanitize((string)($itmSubnetBulkUi['button_label'] ?? 'Generate host IPs')); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
