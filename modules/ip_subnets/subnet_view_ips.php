<?php
/**
 * Subnet detail: shortcuts to IP Addresses module and bulk-generate (no duplicate IP table).
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
                    <button type="submit" name="generate_subnet_ips" value="1" class="btn btn-sm"><?php echo sanitize($itmBulkButtonLabel); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
