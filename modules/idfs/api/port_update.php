<?php
require_once __DIR__ . '/_bootstrap.php';
require_once ROOT_PATH . 'includes/idf_device_port_sort_sql.php';

$data = idf_read_json();
idf_require_csrf($data);

$port_id = (int)($data['port_id'] ?? 0);
if ($port_id <= 0) {
    idf_fail('Invalid port_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT pr.id, i.company_id, pr.management_id, p.equipment_id,
            pr.speed_id, pr.rj45_speed_id, pr.fiber_ports_number, pr.switch_port_numbering_layout_id, pr.poe_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id=?
    "
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $port_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$linkedEquipmentIdRaw = trim((string)($row['equipment_id'] ?? ''));
$linkedEquipmentId = ctype_digit($linkedEquipmentIdRaw) ? (int)$linkedEquipmentIdRaw : 0;
$portManagementId = (int)($row['management_id'] ?? 0);
$portFiberSpeedId = (int)($row['speed_id'] ?? 0);
$portRj45SpeedId = (int)($row['rj45_speed_id'] ?? 0);
$portFiberPortsNumberId = (int)($row['fiber_ports_number'] ?? 0);
$portLayoutId = (int)($row['switch_port_numbering_layout_id'] ?? 0);
$portPoeId = (int)($row['poe_id'] ?? 0);
if ($linkedEquipmentId > 0) {
    $stmtEquipmentManagement = mysqli_prepare(
        $conn,
        "SELECT COALESCE(switch_environment_id, 0) AS switch_environment_id
         FROM equipment
         WHERE id = ? AND company_id = ? AND deleted_at IS NULL
        "
    );
    if ($stmtEquipmentManagement) {
        mysqli_stmt_bind_param($stmtEquipmentManagement, 'ii', $linkedEquipmentId, $company_id);
        mysqli_stmt_execute($stmtEquipmentManagement);
        $resEquipmentManagement = mysqli_stmt_get_result($stmtEquipmentManagement);
        $equipmentManagementRow = $resEquipmentManagement ? mysqli_fetch_assoc($resEquipmentManagement) : null;
        mysqli_stmt_close($stmtEquipmentManagement);
        if ($equipmentManagementRow && (int)($equipmentManagementRow['switch_environment_id'] ?? 0) > 0) {
            $portManagementId = (int)$equipmentManagementRow['switch_environment_id'];
        }
    }
}

$rawPortTypeInput = $data['port_type_id'] ?? ($data['port_type'] ?? '');
$requestedPortTypeLabel = trim((string)($data['port_type_label'] ?? ''));
$port_type_id = idf_resolve_port_type_id($conn, $company_id, $rawPortTypeInput, 'RJ45');
$status_id = idf_resolve_status_id($conn, $company_id, $data['status_id'] ?? ($data['status'] ?? ''), 'Unknown');
$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}
if ($port_type_id <= 0) {
    idf_fail('Invalid port_type');
}

$portTypeName = '';
$stmtPortTypeName = mysqli_prepare($conn, 'SELECT type FROM switch_port_types WHERE company_id = ? AND id = ? LIMIT 1');
if ($stmtPortTypeName) {
    mysqli_stmt_bind_param($stmtPortTypeName, 'ii', $company_id, $port_type_id);
    mysqli_stmt_execute($stmtPortTypeName);
    $resPortTypeName = mysqli_stmt_get_result($stmtPortTypeName);
    $rowPortTypeName = $resPortTypeName ? mysqli_fetch_assoc($resPortTypeName) : null;
    mysqli_stmt_close($stmtPortTypeName);
    if ($rowPortTypeName && trim((string)($rowPortTypeName['type'] ?? '')) !== '') {
        $portTypeName = (string)$rowPortTypeName['type'];
    }
}
if ($portTypeName === '' && $requestedPortTypeLabel !== '') {
    // Why: Keep SFP/RJ45 speed routing correct even when legacy port_type_id rows are out of company scope.
    $portTypeName = $requestedPortTypeLabel;
}
if ($portTypeName === '' && !is_numeric($rawPortTypeInput)) {
    $portTypeName = trim((string)$rawPortTypeInput);
}
if ($portTypeName === '') {
    $portTypeName = 'RJ45';
}
$normalizedPortTypeName = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)$portTypeName));
$isFiberPortType = strpos($normalizedPortTypeName, 'sfp') !== false;

$idfJsonPositiveInt = static function (array $payload, string $key): int {
    if (!array_key_exists($key, $payload)) {
        return 0;
    }
    $raw = $payload[$key];
    if ($raw === null || $raw === '') {
        return 0;
    }
    if (!is_numeric((string)$raw)) {
        return 0;
    }
    $value = (int)$raw;
    return $value > 0 ? $value : 0;
};

$fiber_port_id = $idfJsonPositiveInt($data, 'fiber_port_id');
$fiber_patch_id = $idfJsonPositiveInt($data, 'fiber_patch_id');
$fiber_rack_id = $idfJsonPositiveInt($data, 'fiber_rack_id');

$label = trim((string)($data['label'] ?? ''));
if ($label === '0' || strcasecmp($label, 'null') === 0) {
    $label = '';
}
$connected_to = trim((string)($data['connected_to'] ?? ''));
$vlan_id = idf_resolve_vlan_id($conn, $company_id, $data['vlan_id'] ?? ($data['vlan'] ?? ''));
$speedLookupTable = $isFiberPortType ? 'equipment_fiber' : 'rj45_speed';
$speedLookupColumn = $isFiberPortType ? 'name' : 'cable_type';
$rawSpeedInput = $data['speed_id'] ?? ($data['speed'] ?? '');
$speedInputString = trim((string)$rawSpeedInput);
$speed_id = null;
$rj45_speed_id = null;
if ($speedInputString !== '' && $speedInputString !== '0') {
    $resolvedSpeedId = idf_resolve_named_lookup_id(
        $conn,
        $company_id,
        $speedLookupTable,
        $speedLookupColumn,
        $rawSpeedInput
    );
    if ($resolvedSpeedId !== null && (int)$resolvedSpeedId > 0) {
        if ($isFiberPortType) {
            $speed_id = (int)$resolvedSpeedId;
        } else {
            // Why: RJ45 and fiber speeds use different FK domains in idf_ports.
            $rj45_speed_id = (int)$resolvedSpeedId;
        }
    }
}
if ($isFiberPortType && $fiber_port_id > 0 && ($speed_id === null || (int)$speed_id <= 0)) {
    // Why: Edit Port stores SFP speed in the main speed field while the API also accepts fiber_port_id.
    $speed_id = $fiber_port_id;
}
$rawPoeInput = $data['poe_id'] ?? ($data['poe'] ?? '');
$poeInputString = trim((string)$rawPoeInput);
$poe_id = null;
if ($poeInputString !== '' && $poeInputString !== '0') {
    $poe_id = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_poe', 'name', $rawPoeInput);
}
$rawFiberPortsNumberInput = $data['fiber_ports_number'] ?? '';
$fiberPortsNumberInputString = trim((string)$rawFiberPortsNumberInput);
$fiber_ports_number_id = null;
if ($fiberPortsNumberInputString !== '' && $fiberPortsNumberInputString !== '0') {
    $fiber_ports_number_id = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_fiber_count', 'name', $rawFiberPortsNumberInput);
}
$rawLayoutInput = $data['switch_port_numbering_layout_id'] ?? ($data['switch_port_numbering_layout'] ?? '');
$layoutInputString = trim((string)$rawLayoutInput);
$switch_port_numbering_layout_id = null;
if ($layoutInputString !== '' && $layoutInputString !== '0') {
    $switch_port_numbering_layout_id = idf_resolve_named_lookup_id($conn, $company_id, 'switch_port_numbering_layout', 'name', $rawLayoutInput);
}
$rawManagementInput = $data['management_id'] ?? ($data['management'] ?? '');
$managementInputString = trim((string)$rawManagementInput);
if ($managementInputString !== '') {
    $resolvedManagementId = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_environment', 'name', $rawManagementInput);
    $portManagementId = $resolvedManagementId !== null ? (int)$resolvedManagementId : 0;
}
$to_idf_id = isset($data['to_idf_id']) && is_numeric((string)$data['to_idf_id']) ? (int)$data['to_idf_id'] : 0;
$to_rack_id = isset($data['to_rack_id']) && is_numeric((string)$data['to_rack_id']) ? (int)$data['to_rack_id'] : 0;
$to_location_id = isset($data['to_location_id']) && is_numeric((string)$data['to_location_id']) ? (int)$data['to_location_id'] : 0;
$notes = trim((string)($data['notes'] ?? ''));
$cable_color_id = isset($data['cable_color_id']) ? (int)$data['cable_color_id'] : 0;
$cable_color_name = null;
$cable_hex_color = null;
if ($cable_color_id > 0) {
    $stmtColor = mysqli_prepare(
        $conn,
        "SELECT color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND id = ?
         LIMIT 1"
    );
    if ($stmtColor) {
        mysqli_stmt_bind_param($stmtColor, 'ii', $company_id, $cable_color_id);
        mysqli_stmt_execute($stmtColor);
        $resColor = mysqli_stmt_get_result($stmtColor);
        $colorRow = $resColor ? mysqli_fetch_assoc($resColor) : null;
        mysqli_stmt_close($stmtColor);
        if ($colorRow) {
            $cable_color_name = trim((string)($colorRow['color_name'] ?? ''));
            $cable_hex_color = trim((string)($colorRow['hex_color'] ?? ''));
            if ($cable_color_name === '') {
                $cable_color_name = null;
            }
            if ($cable_hex_color === '') {
                $cable_hex_color = null;
            }
        }
    }
}

$label_val = $label !== '' ? $label : null;
$conn_val = $connected_to !== '' ? $connected_to : null;
$vlan_val = $vlan_id !== null ? (int)$vlan_id : 0;
$speed_val = ($isFiberPortType && $speed_id !== null) ? (int)$speed_id : 0;
$rj45SpeedVal = (!$isFiberPortType && $rj45_speed_id !== null) ? (int)$rj45_speed_id : 0;
$poe_val = $poe_id !== null ? (int)$poe_id : 0;
$fiberPortsNumberVal = $fiber_ports_number_id !== null ? (int)$fiber_ports_number_id : 0;
$layoutVal = $switch_port_numbering_layout_id !== null ? (int)$switch_port_numbering_layout_id : 0;
$notes_val = $notes !== '' ? $notes : null;

$hasRj45SpeedIdColumn = idf_table_has_column($conn, 'idf_ports', 'rj45_speed_id');
$hasFiberPatchIdColumn = idf_table_has_column($conn, 'idf_ports', 'fiber_patch_id');
$hasFiberRackIdColumn = idf_table_has_column($conn, 'idf_ports', 'fiber_rack_id');
$hasToIdfIdColumn = idf_table_has_column($conn, 'idf_ports', 'to_idf_id');
$positionEquipmentJoinSql = itm_idf_position_numeric_equipment_match_sql('p', 'sp.equipment_id');

$sql = "UPDATE idf_ports
        SET port_type=?,
            label=?,
            status_id=?,
            connected_to=?,
            vlan_id=NULLIF(?, 0),
            speed_id=NULLIF(?, 0),";
if ($hasRj45SpeedIdColumn) {
    $sql .= "
            rj45_speed_id=NULLIF(?, 0),";
}
if ($hasFiberPatchIdColumn) {
    $sql .= "
            fiber_patch_id=NULLIF(?, 0),";
}
if ($hasFiberRackIdColumn) {
    $sql .= "
            fiber_rack_id=NULLIF(?, 0),";
}
if ($hasToIdfIdColumn) {
    $sql .= "
            to_idf_id=NULLIF(?, 0),
            to_rack_id=NULLIF(?, 0),
            to_location_id=NULLIF(?, 0),";
}
$sql .= "
            poe_id=NULLIF(?, 0),
            fiber_ports_number=NULLIF(?, 0),
            switch_port_numbering_layout_id=NULLIF(?, 0),
            management_id=NULLIF(?, 0),
            cable_color=?,
            hex_color=?,
            notes=?
        WHERE id=?
        LIMIT 1";

$stmtUpd = mysqli_prepare($conn, $sql);
if (!$stmtUpd) {
    idf_fail('DB error preparing port update: ' . mysqli_error($conn), 500);
}
$bindTypes = 'isisii';
$bindValues = [$port_type_id, $label_val, $status_id, $conn_val, $vlan_val, $speed_val];
if ($hasRj45SpeedIdColumn) {
    $bindTypes .= 'i';
    $bindValues[] = $rj45SpeedVal;
}
if ($hasFiberPatchIdColumn) {
    $bindTypes .= 'i';
    $bindValues[] = $fiber_patch_id;
}
if ($hasFiberRackIdColumn) {
    $bindTypes .= 'i';
    $bindValues[] = $fiber_rack_id;
}
if ($hasToIdfIdColumn) {
    $bindTypes .= 'iii';
    $bindValues[] = $to_idf_id;
    $bindValues[] = $to_rack_id;
    $bindValues[] = $to_location_id;
}
$bindTypes .= 'iiiisssi';
$bindValues[] = $poe_val;
$bindValues[] = $fiberPortsNumberVal;
$bindValues[] = $layoutVal;
$bindValues[] = $portManagementId;
$bindValues[] = $cable_color_name;
$bindValues[] = $cable_hex_color;
$bindValues[] = $notes_val;
$bindValues[] = $port_id;
mysqli_stmt_bind_param($stmtUpd, $bindTypes, ...$bindValues);
if (!mysqli_stmt_execute($stmtUpd)) {
    idf_fail('DB error updating port: ' . mysqli_stmt_error($stmtUpd), 500);
}
mysqli_stmt_close($stmtUpd);

if ($linkedEquipmentId > 0) {
    $stmtEquipmentSync = mysqli_prepare(
        $conn,
        "UPDATE equipment
         SET switch_fiber_id = NULLIF(?, 0),
             switch_rj45_id = NULLIF(?, 0),
             switch_fiber_ports_number = NULLIF(?, 0),
             switch_port_numbering_layout_id = NULLIF(?, 0),
             switch_poe_id = NULLIF(?, 0),
             switch_environment_id = NULLIF(?, 0)
         WHERE id = ? AND company_id = ?
         LIMIT 1"
    );
    if ($stmtEquipmentSync) {
        $equipmentFiberSpeedId = $speed_val > 0 ? $speed_val : $portFiberSpeedId;
        $equipmentRj45SpeedId = $rj45SpeedVal > 0 ? $rj45SpeedVal : $portRj45SpeedId;
        $equipmentFiberPortsNumberId = $fiberPortsNumberVal > 0 ? $fiberPortsNumberVal : $portFiberPortsNumberId;
        $equipmentLayoutId = $layoutVal > 0 ? $layoutVal : $portLayoutId;
        $equipmentPoeId = $poe_val > 0 ? $poe_val : $portPoeId;
        mysqli_stmt_bind_param($stmtEquipmentSync, 'iiiiiiii', $equipmentFiberSpeedId, $equipmentRj45SpeedId, $equipmentFiberPortsNumberId, $equipmentLayoutId, $equipmentPoeId, $portManagementId, $linkedEquipmentId, $company_id);
        mysqli_stmt_execute($stmtEquipmentSync);
        mysqli_stmt_close($stmtEquipmentSync);
    }

    $stmtSwitchManagementSync = mysqli_prepare(
        $conn,
        "UPDATE switch_ports
         SET fiber_port_id = NULLIF(?, 0),
             management_id = NULLIF(?, 0)
         WHERE company_id = ? AND equipment_id = ?"
    );
    if ($stmtSwitchManagementSync) {
        $switchFiberPortId = $speed_val > 0 ? $speed_val : $portFiberSpeedId;
        mysqli_stmt_bind_param($stmtSwitchManagementSync, 'iiii', $switchFiberPortId, $portManagementId, $company_id, $linkedEquipmentId);
        mysqli_stmt_execute($stmtSwitchManagementSync);
        mysqli_stmt_close($stmtSwitchManagementSync);
    }
}

$stmtSwitchSync = mysqli_prepare(
    $conn,
    "UPDATE switch_ports sp
     JOIN idf_ports pr ON pr.id = ?
     JOIN idf_positions p
       ON p.company_id = pr.company_id
      AND (
           p.id = pr.position_id
           OR p.position_no = pr.position_id
      )
     LEFT JOIN switch_port_types spt ON spt.id = pr.port_type AND spt.company_id = pr.company_id
     SET sp.{$switchPortLabelColumn} = ?,
         sp.status_id = ?,
         sp.color_id = NULLIF(?, 0),
         sp.rj45_speed_id = NULLIF(?, 0),
         sp.fiber_port_id = NULLIF(?, 0),
         sp.fiber_patch_id = NULLIF(?, 0),
         sp.fiber_rack_id = NULLIF(?, 0),
         sp.vlan_id = NULLIF(?, 0),
         sp.to_idf_id = NULLIF(?, 0),
         sp.to_rack_id = NULLIF(?, 0),
         sp.to_location_id = NULLIF(?, 0),
         sp.comments = ?
     WHERE sp.company_id = ?
       AND p.company_id = sp.company_id
       AND {$positionEquipmentJoinSql}
       AND sp.port_number = pr.port_no
       AND (
            CONVERT(CAST(sp.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(CAST(pr.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR (
                sp.port_type REGEXP '^[0-9]+$'
                AND CAST(sp.port_type AS UNSIGNED) = pr.port_type
            )
            OR
            CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(COALESCE(spt.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR (
                sp.port_type REGEXP '^[0-9]+$'
                AND CAST(sp.port_type AS UNSIGNED) = spt.id
            )
            OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
       )
    "
);
if (!$stmtSwitchSync) {
    idf_fail('DB error preparing switch port sync: ' . mysqli_error($conn), 500);
}
if ($stmtSwitchSync) {
    $switchColorId = $cable_color_id > 0 ? $cable_color_id : 0;
    $switchRj45SpeedSyncId = $rj45SpeedVal > 0 ? $rj45SpeedVal : 0;
    $switchFiberPortSyncId = $fiber_port_id > 0 ? $fiber_port_id : ($isFiberPortType && $speed_val > 0 ? $speed_val : 0);
    mysqli_stmt_bind_param(
        $stmtSwitchSync,
        'isiiiiiiiiiisi',
        $port_id,
        $label_val,
        $status_id,
        $switchColorId,
        $switchRj45SpeedSyncId,
        $switchFiberPortSyncId,
        $fiber_patch_id,
        $fiber_rack_id,
        $vlan_val,
        $to_idf_id,
        $to_rack_id,
        $to_location_id,
        $notes_val,
        $company_id
    );
    if (!mysqli_stmt_execute($stmtSwitchSync)) {
        idf_fail('DB error syncing switch port: ' . mysqli_stmt_error($stmtSwitchSync), 500);
    }
    mysqli_stmt_close($stmtSwitchSync);
}

// Why: Editing one side of a linked port must propagate color/status to the paired side and its switch mirror too.
$linkedPeerPortIds = [];
$stmtPeerPorts = mysqli_prepare(
    $conn,
    "SELECT DISTINCT
        CASE
            WHEN l.port_id_a = ? THEN l.port_id_b
            WHEN l.port_id_b = ? THEN l.port_id_a
            ELSE NULL
        END AS peer_port_id
     FROM idf_links l
     WHERE l.company_id = ?
       AND (l.port_id_a = ? OR l.port_id_b = ?)"
);
if ($stmtPeerPorts) {
    mysqli_stmt_bind_param($stmtPeerPorts, 'iiiii', $port_id, $port_id, $company_id, $port_id, $port_id);
    mysqli_stmt_execute($stmtPeerPorts);
    $resPeerPorts = mysqli_stmt_get_result($stmtPeerPorts);
    while ($resPeerPorts && ($peerRow = mysqli_fetch_assoc($resPeerPorts))) {
        $peerPortId = (int)($peerRow['peer_port_id'] ?? 0);
        if ($peerPortId > 0) {
            $linkedPeerPortIds[] = $peerPortId;
        }
    }
    mysqli_stmt_close($stmtPeerPorts);
}
$linkedPeerPortIds = array_values(array_unique($linkedPeerPortIds));

if ($linkedPeerPortIds) {
    $peerPortSql = "UPDATE idf_ports
         SET status_id = ?,
             cable_color = ?,
             hex_color = ?,
             vlan_id = NULLIF(?, 0),
             speed_id = NULLIF(?, 0)";
    if ($hasRj45SpeedIdColumn) {
        $peerPortSql .= ",
             rj45_speed_id = NULLIF(?, 0)";
    }
    if ($hasFiberPatchIdColumn) {
        $peerPortSql .= ",
             fiber_patch_id = NULLIF(?, 0)";
    }
    if ($hasFiberRackIdColumn) {
        $peerPortSql .= ",
             fiber_rack_id = NULLIF(?, 0)";
    }
    $peerPortSql .= "
         WHERE id = ?
         LIMIT 1";
    $stmtPeerPortUpdate = mysqli_prepare($conn, $peerPortSql);
    if (!$stmtPeerPortUpdate) {
        idf_fail('DB error preparing peer port update: ' . mysqli_error($conn), 500);
    }
    foreach ($linkedPeerPortIds as $peerPortId) {
        $peerBindTypes = 'issii';
        $peerBindValues = [$status_id, $cable_color_name, $cable_hex_color, $vlan_val, $speed_val];
        if ($hasRj45SpeedIdColumn) {
            $peerBindTypes .= 'i';
            $peerBindValues[] = $rj45SpeedVal;
        }
        if ($hasFiberPatchIdColumn) {
            $peerBindTypes .= 'i';
            $peerBindValues[] = $fiber_patch_id;
        }
        if ($hasFiberRackIdColumn) {
            $peerBindTypes .= 'i';
            $peerBindValues[] = $fiber_rack_id;
        }
        $peerBindTypes .= 'i';
        $peerBindValues[] = $peerPortId;
        mysqli_stmt_bind_param($stmtPeerPortUpdate, $peerBindTypes, ...$peerBindValues);
        if (!mysqli_stmt_execute($stmtPeerPortUpdate)) {
            idf_fail('DB error updating linked peer port: ' . mysqli_stmt_error($stmtPeerPortUpdate), 500);
        }
    }
    mysqli_stmt_close($stmtPeerPortUpdate);

    $stmtPeerSwitchSync = mysqli_prepare(
        $conn,
        "UPDATE switch_ports sp
         JOIN idf_ports pr ON pr.id = ?
         JOIN idf_positions p
           ON p.company_id = pr.company_id
          AND (
               p.id = pr.position_id
               OR p.position_no = pr.position_id
          )
         LEFT JOIN switch_port_types spt ON spt.id = pr.port_type AND spt.company_id = pr.company_id
         SET sp.status_id = ?,
             sp.color_id = NULLIF(?, 0),
             sp.comments = ?,
             sp.{$switchPortLabelColumn} = ?
         WHERE sp.company_id = ?
           AND p.company_id = sp.company_id
           AND {$positionEquipmentJoinSql}
           AND sp.port_number = pr.port_no
           AND (
                CONVERT(CAST(sp.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(pr.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = pr.port_type
                )
                OR
                CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(COALESCE(spt.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = spt.id
                )
                OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           )
        "
    );
    if (!$stmtPeerSwitchSync) {
        idf_fail('DB error preparing peer switch sync: ' . mysqli_error($conn), 500);
    }
    if ($stmtPeerSwitchSync) {
        $peerSwitchColorId = $cable_color_id > 0 ? $cable_color_id : 0;
        foreach ($linkedPeerPortIds as $peerPortId) {
            mysqli_stmt_bind_param($stmtPeerSwitchSync, 'iiissi', $peerPortId, $status_id, $peerSwitchColorId, $notes_val, $label_val, $company_id);
            mysqli_stmt_execute($stmtPeerSwitchSync);
        }
        mysqli_stmt_close($stmtPeerSwitchSync);
    }
}

$stmtLinkUpdate = mysqli_prepare(
    $conn,
    "UPDATE idf_links
     SET cable_color_id = NULLIF(?, 0),
         cable_color_hex = ?
     WHERE port_id_a = ? OR port_id_b = ?"
);
if ($stmtLinkUpdate) {
    mysqli_stmt_bind_param($stmtLinkUpdate, 'isii', $cable_color_id, $cable_hex_color, $port_id, $port_id);
    if (!mysqli_stmt_execute($stmtLinkUpdate)) {
        idf_fail('DB error updating link cable color: ' . mysqli_stmt_error($stmtLinkUpdate), 500);
    }
    mysqli_stmt_close($stmtLinkUpdate);
}

$stmtLinkMetaSync = mysqli_prepare(
    $conn,
    "UPDATE idf_links l
     SET l.equipment_label = ?,
         l.equipment_status_id = ?,
         l.equipment_vlan_id = NULLIF(?, 0),
         l.equipment_rj45_speed_id = NULLIF(?, 0),
         l.equipment_fiber_port_id = NULLIF(?, 0),
         l.equipment_fiber_patch_id = NULLIF(?, 0),
         l.equipment_fiber_rack_id = NULLIF(?, 0),
         l.equipment_to_idf_id = NULLIF(?, 0),
         l.equipment_to_rack_id = NULLIF(?, 0),
         l.equipment_to_location_id = NULLIF(?, 0),
         l.equipment_comments = ?,
         l.equipment_color_id = NULLIF(?, 0)
     WHERE l.company_id = ?
       AND (l.port_id_a = ? OR l.port_id_b = ?)"
);
if ($stmtLinkMetaSync) {
    $linkColorId = $cable_color_id > 0 ? $cable_color_id : 0;
    $linkRj45SpeedId = $rj45SpeedVal > 0 ? $rj45SpeedVal : 0;
    $linkFiberPortId = $fiber_port_id > 0 ? $fiber_port_id : ($isFiberPortType && $speed_val > 0 ? $speed_val : 0);
    mysqli_stmt_bind_param(
        $stmtLinkMetaSync,
        'siiiiiiiiisiiii',
        $label_val,
        $status_id,
        $vlan_val,
        $linkRj45SpeedId,
        $linkFiberPortId,
        $fiber_patch_id,
        $fiber_rack_id,
        $to_idf_id,
        $to_rack_id,
        $to_location_id,
        $notes_val,
        $linkColorId,
        $company_id,
        $port_id,
        $port_id
    );
    if (!mysqli_stmt_execute($stmtLinkMetaSync)) {
        idf_fail('DB error syncing link metadata: ' . mysqli_stmt_error($stmtLinkMetaSync), 500);
    }
    mysqli_stmt_close($stmtLinkMetaSync);
}

idf_ok();
