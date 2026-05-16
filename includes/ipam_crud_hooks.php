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
 * Why: Failed saves must re-render raw POST values, not SQL fragments like '10.0.0.0/24'.
 */
function itm_ipam_form_display_value(string $fieldName, $dataValue, bool $preferPost): string
{
    if ($preferPost && isset($_POST[$fieldName]) && !is_array($_POST[$fieldName])) {
        return itm_ipam_trim_user_input((string)$_POST[$fieldName]);
    }

    $val = (string)$dataValue;
    if ($val === 'NULL' || $val === '') {
        return '';
    }
    if (strlen($val) >= 2 && $val[0] === "'" && substr($val, -1) === "'") {
        return stripcslashes(substr($val, 1, -1));
    }

    return $val;
}

/**
 * Validate/normalize POST before the generic CRUD loop builds SQL fragments.
 */
function itm_ipam_prepare_post_before_crud(
    mysqli $conn,
    string $table,
    int $company_id,
    array &$post,
    string $action,
    int $editId,
    array &$errors
): void {
    if ($table === 'ip_subnets') {
        $post['cidr'] = itm_ipam_trim_user_input($post['cidr'] ?? '');
        $parsed = itm_ipam_parse_cidr((string)$post['cidr']);
        if (!$parsed['ok']) {
            $errors[] = (string)$parsed['error'];
            return;
        }

        $post['cidr'] = (string)$parsed['cidr'];

        if (!empty($parsed['normalized_from'])) {
            $_SESSION['crud_success'] = 'CIDR was adjusted to the network address '
                . (string)$parsed['cidr']
                . ' (you entered '
                . (string)$parsed['normalized_from']
                . ').';
        }

        foreach (['gateway_ip', 'dns1_ip', 'dns2_ip'] as $ipField) {
            if (!array_key_exists($ipField, $post)) {
                continue;
            }
            $raw = itm_ipam_trim_user_input($post[$ipField] ?? '');
            $post[$ipField] = $raw;
            if ($raw === '') {
                continue;
            }
            if (!itm_ipam_is_valid_ipv4($raw)) {
                $errors[] = ucwords(str_replace('_', ' ', $ipField)) . ' must be a valid IPv4 address.';
            }
        }

        if ($company_id > 0 && !$errors) {
            $exclude = ($action === 'edit' && $editId > 0) ? $editId : 0;
            $stmtDup = mysqli_prepare(
                $conn,
                'SELECT id FROM ip_subnets WHERE company_id = ? AND cidr = ? AND id <> ? LIMIT 1'
            );
            if ($stmtDup) {
                mysqli_stmt_bind_param($stmtDup, 'isi', $company_id, $post['cidr'], $exclude);
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
        $post['ip_text'] = itm_ipam_trim_user_input($post['ip_text'] ?? '');
        if ($post['ip_text'] === '') {
            $errors[] = 'IP address is required.';
            return;
        }
        if (!itm_ipam_is_valid_ipv4($post['ip_text'])) {
            $errors[] = 'IP address must be a valid IPv4 address.';
            return;
        }

        $status = strtolower(itm_ipam_trim_user_input($post['status'] ?? 'free'));
        $allowed = ['free', 'used', 'reserved', 'gateway', 'dns', 'dhcp', 'other'];
        if (!in_array($status, $allowed, true)) {
            $status = 'free';
        }
        $post['status'] = $status;

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
                mysqli_stmt_bind_param($stmtDup, 'isi', $subnetId, $post['ip_text'], $exclude);
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
 * Add hidden/derived subnet columns to SQL payload after the generic loop.
 */
function itm_ipam_apply_derived_sql_to_data(mysqli $conn, string $table, array &$data, array $post): void
{
    if ($table !== 'ip_subnets') {
        return;
    }

    $parsed = itm_ipam_parse_cidr(itm_ipam_trim_user_input($post['cidr'] ?? ''));
    if (!$parsed['ok']) {
        return;
    }

    $data['network_ip'] = "'" . mysqli_real_escape_string($conn, (string)$parsed['network_ip']) . "'";
    $data['prefix_length'] = (string)(int)$parsed['prefix_length'];
}

/**
 * Post-save side effects (equipment.ip_address sync, gateway row hints).
 * Does not read or write equipment.notes or ip_addresses.notes except via normal CRUD fields.
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
        $ipText = itm_ipam_trim_user_input($post['ip_text'] ?? '');
        $status = strtolower(trim((string)($post['status'] ?? 'free')));
        if ($equipmentId > 0) {
            itm_ipam_sync_equipment_ip_address($conn, $company_id, $equipmentId, $ipText, $status);
        }
        return;
    }

    if ($table === 'ip_subnets') {
        $gatewayIp = itm_ipam_trim_user_input($post['gateway_ip'] ?? '');
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
