<?php
/**
 * IPAM helpers: CIDR parsing, IPv4 validation, and equipment sync.
 */

/**
 * Why: Subnet rows store normalized network/prefix derived from human-entered CIDR notation.
 */
function itm_ipam_parse_cidr(string $cidr): array
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return ['ok' => false, 'error' => 'CIDR is required.'];
    }

    if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})\s*/\s*(\d{1,2})$#', $cidr, $matches)) {
        return ['ok' => false, 'error' => 'CIDR must look like 10.0.0.0/24.'];
    }

    $networkIp = $matches[1];
    $prefixLength = (int)$matches[2];
    if (!itm_ipam_is_valid_ipv4($networkIp)) {
        return ['ok' => false, 'error' => 'Network address is not a valid IPv4 address.'];
    }
    if ($prefixLength < 0 || $prefixLength > 32) {
        return ['ok' => false, 'error' => 'Prefix length must be between 0 and 32.'];
    }

    $networkLong = ip2long($networkIp);
    if ($networkLong === false) {
        return ['ok' => false, 'error' => 'Network address is not a valid IPv4 address.'];
    }

    if ($prefixLength < 32) {
        $mask = (0xFFFFFFFF << (32 - $prefixLength)) & 0xFFFFFFFF;
        if (($networkLong & (~$mask & 0xFFFFFFFF)) !== 0) {
            return ['ok' => false, 'error' => 'Network address is not aligned to the CIDR prefix.'];
        }
    }

    return [
        'ok' => true,
        'cidr' => $networkIp . '/' . $prefixLength,
        'network_ip' => $networkIp,
        'prefix_length' => $prefixLength,
        'error' => '',
    ];
}

function itm_ipam_is_valid_ipv4(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '') {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }
    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }
    foreach ($parts as $part) {
        if ($part === '' || !ctype_digit($part) || (int)$part > 255) {
            return false;
        }
    }
    return true;
}

/**
 * Why: Keep equipment.ip_address aligned when an IPAM row is assigned to an asset.
 */
function itm_ipam_sync_equipment_ip_address(
    mysqli $conn,
    int $company_id,
    int $equipment_id,
    string $ip_text,
    string $status
): void {
    if ($company_id <= 0 || $equipment_id <= 0) {
        return;
    }

    $ip_text = trim($ip_text);
    $status = strtolower(trim($status));
    $clearIp = ($ip_text === '' || !in_array($status, ['used', 'reserved', 'dhcp'], true));

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE equipment SET ip_address = ? WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return;
    }

    $value = $clearIp ? null : $ip_text;
    mysqli_stmt_bind_param($stmt, 'sii', $value, $equipment_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
