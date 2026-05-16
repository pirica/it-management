<?php
/**
 * IPAM CRUD hooks for ip_subnets and ip_addresses modules.
 */

require_once __DIR__ . '/ipam_helpers.php';

/**
 * Why: VLAN FK labels must show vlan number + name, not a missing "name" column.
 */
function itm_ipam_fk_options_override(mysqli $conn, array $fk, int $company_id, string $columnName): ?array
{
    $refTable = (string)($fk['REFERENCED_TABLE_NAME'] ?? '');
    if ($refTable === 'vlans' && $columnName === 'vlan_id') {
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id,
                    TRIM(CONCAT(COALESCE(vlan_number, ''), CASE WHEN vlan_number IS NULL THEN '' ELSE ' - ' END, vlan_name)) AS label
             FROM vlans
             WHERE company_id = ?
             ORDER BY vlan_number ASC, vlan_name ASC"
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = ['id' => (int)$row['id'], 'label' => (string)$row['label']];
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    if ($refTable === 'equipment' && $columnName === 'equipment_id') {
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id,
                    TRIM(CONCAT(COALESCE(hostname, ''), CASE WHEN hostname IS NOT NULL AND hostname <> '' AND name IS NOT NULL AND name <> '' THEN ' (' ELSE '' END, COALESCE(name, ''), CASE WHEN hostname IS NOT NULL AND hostname <> '' AND name IS NOT NULL AND name <> '' THEN ')' ELSE '' END)) AS label
             FROM equipment
             WHERE company_id = ?
             ORDER BY name ASC, hostname ASC"
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') {
                $label = 'Equipment #' . (int)$row['id'];
            }
            $rows[] = ['id' => (int)$row['id'], 'label' => $label];
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    if ($refTable === 'ip_subnets' && $columnName === 'subnet_id') {
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            "SELECT s.id,
                    TRIM(CONCAT(s.cidr, CASE WHEN v.vlan_name IS NULL OR v.vlan_name = '' THEN '' ELSE CONCAT(' (', v.vlan_name, ')') END)) AS label
             FROM ip_subnets s
             LEFT JOIN vlans v ON v.id = s.vlan_id AND v.company_id = s.company_id
             WHERE s.company_id = ?
             ORDER BY s.cidr ASC"
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = ['id' => (int)$row['id'], 'label' => (string)$row['label']];
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    return null;
}

/**
 * Resolve FK labels for IPAM-related dropdowns and list cells.
 */
function itm_ipam_fk_label_by_id(mysqli $conn, array $fk, int $company_id, int $id, string $columnName): string
{
    if ($id <= 0) {
        return '';
    }

    $options = itm_ipam_fk_options_override($conn, $fk, $company_id, $columnName);
    if (is_array($options)) {
        foreach ($options as $opt) {
            if ((int)($opt['id'] ?? 0) === $id) {
                return (string)($opt['label'] ?? '');
            }
        }
    }

    return '';
}

/**
 * Normalize/validate IPAM rows before INSERT/UPDATE SQL is built.
 *
 * @param array<string, string> $data  Column => SQL fragment values ('NULL', quoted strings, numbers)
 * @param array<int, string>    $errors
 */
function itm_ipam_normalize_crud_row(
    mysqli $conn,
    string $table,
    int $company_id,
    array &$data,
    array $post,
    string $action,
    int $editId,
    array &$errors
): void {
    if ($table === 'ip_subnets') {
        $cidrRaw = trim((string)($post['cidr'] ?? ''));
        $parsed = itm_ipam_parse_cidr($cidrRaw);
        if (!$parsed['ok']) {
            $errors[] = (string)$parsed['error'];
            return;
        }

        $data['cidr'] = "'" . mysqli_real_escape_string($conn, (string)$parsed['cidr']) . "'";
        $data['network_ip'] = "'" . mysqli_real_escape_string($conn, (string)$parsed['network_ip']) . "'";
        $data['prefix_length'] = (string)(int)$parsed['prefix_length'];

        if (!empty($parsed['normalized_from'])) {
            $_SESSION['crud_success'] = 'CIDR was adjusted to the network address '
                . (string)$parsed['cidr']
                . ' (you entered '
                . (string)$parsed['normalized_from']
                . ').';
        }

        foreach (['gateway_ip', 'dns1_ip', 'dns2_ip'] as $ipField) {
            $raw = trim((string)($post[$ipField] ?? ''));
            if ($raw === '') {
                $data[$ipField] = 'NULL';
                continue;
            }
            if (!itm_ipam_is_valid_ipv4($raw)) {
                $errors[] = ucwords(str_replace('_', ' ', $ipField)) . ' must be a valid IPv4 address.';
                continue;
            }
            $data[$ipField] = "'" . mysqli_real_escape_string($conn, $raw) . "'";
        }

        if ($company_id > 0 && $parsed['cidr'] !== '') {
            $exclude = ($action === 'edit' && $editId > 0) ? $editId : 0;
            $stmtDup = mysqli_prepare(
                $conn,
                'SELECT id FROM ip_subnets WHERE company_id = ? AND cidr = ? AND id <> ? LIMIT 1'
            );
            if ($stmtDup) {
                mysqli_stmt_bind_param($stmtDup, 'isi', $company_id, $parsed['cidr'], $exclude);
                mysqli_stmt_execute($stmtDup);
                $resDup = mysqli_stmt_get_result($stmtDup);
                $dup = $resDup && mysqli_num_rows($resDup) > 0;
                mysqli_stmt_close($stmtDup);
                if ($dup) {
                    $errors[] = 'CIDR must be unique for this company.';
                }
            }
        }

        return;
    }

    if ($table === 'ip_addresses') {
        $ipText = trim((string)($post['ip_text'] ?? ''));
        if ($ipText === '') {
            $errors[] = 'IP address is required.';
            return;
        }
        if (!itm_ipam_is_valid_ipv4($ipText)) {
            $errors[] = 'IP address must be a valid IPv4 address.';
            return;
        }
        $data['ip_text'] = "'" . mysqli_real_escape_string($conn, $ipText) . "'";

        $status = strtolower(trim((string)($post['status'] ?? 'free')));
        $allowed = ['free', 'used', 'reserved', 'gateway', 'dns', 'dhcp', 'other'];
        if (!in_array($status, $allowed, true)) {
            $status = 'free';
        }
        $data['status'] = "'" . mysqli_real_escape_string($conn, $status) . "'";

        $subnetId = (int)($post['subnet_id'] ?? 0);
        if ($subnetId > 0 && $company_id > 0) {
            $stmtSubnet = mysqli_prepare(
                $conn,
                'SELECT id FROM ip_subnets WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($stmtSubnet) {
                mysqli_stmt_bind_param($stmtSubnet, 'ii', $subnetId, $company_id);
                mysqli_stmt_execute($stmtSubnet);
                $resSubnet = mysqli_stmt_get_result($stmtSubnet);
                $subnetOk = $resSubnet && mysqli_num_rows($resSubnet) === 1;
                mysqli_stmt_close($stmtSubnet);
                if (!$subnetOk) {
                    $errors[] = 'Selected subnet was not found for this company.';
                    return;
                }
            }

            $exclude = ($action === 'edit' && $editId > 0) ? $editId : 0;
            $stmtDup = mysqli_prepare(
                $conn,
                'SELECT id FROM ip_addresses WHERE subnet_id = ? AND ip_text = ? AND id <> ? LIMIT 1'
            );
            if ($stmtDup) {
                mysqli_stmt_bind_param($stmtDup, 'isi', $subnetId, $ipText, $exclude);
                mysqli_stmt_execute($stmtDup);
                $resDup = mysqli_stmt_get_result($stmtDup);
                $dup = $resDup && mysqli_num_rows($resDup) > 0;
                mysqli_stmt_close($stmtDup);
                if ($dup) {
                    $errors[] = 'This IP is already assigned in the selected subnet.';
                }
            }
        }
    }
}

/**
 * Post-save side effects (equipment.ip_address sync, gateway row hints).
 */
function itm_ipam_after_crud_save(
    mysqli $conn,
    string $table,
    int $company_id,
    int $recordId,
    array $post
): void {
    if ($company_id <= 0 || $recordId <= 0) {
        return;
    }

    if ($table === 'ip_addresses') {
        $equipmentId = (int)($post['equipment_id'] ?? 0);
        $ipText = trim((string)($post['ip_text'] ?? ''));
        $status = strtolower(trim((string)($post['status'] ?? 'free')));
        if ($equipmentId > 0) {
            itm_ipam_sync_equipment_ip_address($conn, $company_id, $equipmentId, $ipText, $status);
        }
        return;
    }

    if ($table === 'ip_subnets') {
        $gatewayIp = trim((string)($post['gateway_ip'] ?? ''));
        $subnetId = $recordId;
        if ($gatewayIp !== '' && itm_ipam_is_valid_ipv4($gatewayIp)) {
            $stmtExists = mysqli_prepare(
                $conn,
                "SELECT id FROM ip_addresses WHERE subnet_id = ? AND ip_text = ? LIMIT 1"
            );
            if ($stmtExists) {
                mysqli_stmt_bind_param($stmtExists, 'is', $subnetId, $gatewayIp);
                mysqli_stmt_execute($stmtExists);
                $resExists = mysqli_stmt_get_result($stmtExists);
                $exists = $resExists && mysqli_num_rows($resExists) > 0;
                mysqli_stmt_close($stmtExists);
                if (!$exists) {
                    $status = 'gateway';
                    $stmtIns = mysqli_prepare(
                        $conn,
                        "INSERT INTO ip_addresses (company_id, subnet_id, ip_text, status, is_gateway, active)
                         VALUES (?, ?, ?, ?, 1, 1)"
                    );
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, 'iiss', $company_id, $subnetId, $gatewayIp, $status);
                        mysqli_stmt_execute($stmtIns);
                        mysqli_stmt_close($stmtIns);
                    }
                }
            }
        }
    }
}
