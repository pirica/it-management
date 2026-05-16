<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$portA = (int)($data['port_id_a'] ?? 0);
$portB = (int)($data['port_id_b'] ?? 0);
$equipmentIdRaw = trim((string)($data['equipment_id'] ?? ''));
$equipmentId = ctype_digit($equipmentIdRaw) ? (int)$equipmentIdRaw : 0;
$equipmentToken = null;
if ($equipmentIdRaw !== '' && $equipmentId === 0 && preg_match('/^[0-9]{4}-[0-9]{4}$/', $equipmentIdRaw)) {
    $equipmentToken = $equipmentIdRaw;
}
$switchPortId = isset($data['switch_port_id']) && $data['switch_port_id'] !== null ? (int)$data['switch_port_id'] : 0;
$cableColorId = isset($data['cable_color_id']) ? (int)$data['cable_color_id'] : 0;
$label = trim((string)($data['cable_label'] ?? ''));
if ($label === '0' || strcasecmp($label, 'null') === 0) {
    $label = '';
}
$notes = trim((string)($data['notes'] ?? ''));
$status_id = idf_resolve_status_id($conn, $company_id, $data['status_id'] ?? ($data['status'] ?? ''), 'Used');
$linkedEquipmentPort = trim((string)($data['linked_equipment_port'] ?? ''));
$linkedDestinationPort = trim((string)($data['linked_destination_port'] ?? ''));
$linkedCableColorName = trim((string)($data['linked_cable_color'] ?? ''));
$linkedCableColorHexRaw = trim((string)($data['linked_cable_color_hex'] ?? ''));
$rj45SpeedId = isset($data['rj45_speed_id']) ? (int)$data['rj45_speed_id'] : 0;
$poeId = isset($data['poe_id']) && is_numeric((string)$data['poe_id']) ? (int)$data['poe_id'] : 0;
if ($poeId < 0) {
    $poeId = 0;
}
$fiberPortId = isset($data['fiber_port_id']) ? (int)$data['fiber_port_id'] : 0;
$fiberPatchId = isset($data['fiber_patch_id']) ? (int)$data['fiber_patch_id'] : 0;
$fiberRackId = isset($data['fiber_rack_id']) ? (int)$data['fiber_rack_id'] : 0;
$toIdfId = isset($data['to_idf_id']) ? (int)$data['to_idf_id'] : 0;
$toRackId = isset($data['to_rack_id']) ? (int)$data['to_rack_id'] : 0;
$toLocationId = isset($data['to_location_id']) ? (int)$data['to_location_id'] : 0;
$requestVlanId = (isset($data['vlan_id']) && is_numeric((string)$data['vlan_id'])) ? (int)$data['vlan_id'] : 0;
if ($requestVlanId < 0) {
    $requestVlanId = 0;
}
$requestCableColorName = trim((string)($data['cable_color_name'] ?? ''));
$requestCableColorHexRaw = trim((string)($data['cable_color_hex'] ?? ''));
$resolvedColorNameInput = $linkedCableColorName !== '' ? $linkedCableColorName : $requestCableColorName;
$resolvedColorHexInputRaw = $linkedCableColorHexRaw !== '' ? $linkedCableColorHexRaw : $requestCableColorHexRaw;
$resolvedColorHexInput = '';
if (preg_match('/^#[0-9A-Fa-f]{6}$/', $resolvedColorHexInputRaw)) {
    $resolvedColorHexInput = strtoupper($resolvedColorHexInputRaw);
}
$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}

if ($portA <= 0 || $portB <= 0) {
    idf_fail('Invalid port ids');
}
if ($portA === $portB) {
    idf_fail('Cannot link a port to itself');
}

if ($switchPortId > 0 && $equipmentId <= 0) {
    idf_fail('Equipment is required when selecting an equipment port');
}

$portMetaA = idf_resolve_port_position($conn, $company_id, $portA);
$portMetaB = idf_resolve_port_position($conn, $company_id, $portB);
if (!$portMetaA || !$portMetaB) {
    idf_fail('Port not found', 404);
}
if ((int)($portMetaA['company_id'] ?? 0) !== $company_id || (int)($portMetaB['company_id'] ?? 0) !== $company_id) {
    idf_fail('Forbidden', 403);
}

$positionSeen = [
    $portA => (int)($portMetaA['position_id'] ?? 0),
    $portB => (int)($portMetaB['position_id'] ?? 0),
];
$positionNoSeen = [
    $portA => (int)($portMetaA['position_no'] ?? 0),
    $portB => (int)($portMetaB['position_no'] ?? 0),
];
$portNoSeen = [
    $portA => (int)($portMetaA['port_no'] ?? 0),
    $portB => (int)($portMetaB['port_no'] ?? 0),
];
$deviceSeen = [
    $portA => (string)($portMetaA['device_name'] ?? ''),
    $portB => (string)($portMetaB['device_name'] ?? ''),
];
$positionEquipmentSeen = [
    $portA => trim((string)($portMetaA['position_equipment_id'] ?? '')),
    $portB => trim((string)($portMetaB['position_equipment_id'] ?? '')),
];

if ($positionSeen[$portA] === $positionSeen[$portB]) {
    $deviceName = trim((string)($deviceSeen[$portA] ?? 'this device'));
    $positionNo = (int)($positionNoSeen[$portA] ?? 0);
    $samePositionLabel = $positionNo > 0
        ? 'Pos ' . $positionNo . ' • ' . $deviceName
        : $deviceName;
    idf_fail(
        'Cannot link two ports on the same rack position (' . $samePositionLabel . '). '
        . 'Choose a port on a different rack position to avoid switching loops.'
    );
}

$portTypeLabels = idf_fetch_port_type_labels($conn, $company_id, [$portA, $portB]);
$portTypeLabelA = (string)($portTypeLabels[$portA] ?? 'RJ45');
$portTypeLabelB = (string)($portTypeLabels[$portB] ?? 'RJ45');
if (!idf_ports_are_link_compatible($portTypeLabelA, $portTypeLabelB)) {
    idf_fail(idf_port_type_link_mismatch_message($portTypeLabelA, $portTypeLabelB));
}

$positionEquipmentSerialSeen = [];
$positionEquipmentIds = array_values(array_unique(array_filter(array_map(static function ($id): int {
    $idString = trim((string)$id);
    return ctype_digit($idString) ? (int)$idString : 0;
}, $positionEquipmentSeen), static function ($id) {
    return $id > 0;
})));
if ($positionEquipmentIds) {
    $list = implode(',', $positionEquipmentIds);
    $resPositionEquipment = mysqli_query(
        $conn,
        "SELECT id, serial_number
         FROM equipment
         WHERE company_id = $company_id
           AND id IN ($list)"
    );
    while ($resPositionEquipment && ($row = mysqli_fetch_assoc($resPositionEquipment))) {
        $equipmentIdKey = (int)($row['id'] ?? 0);
        if ($equipmentIdKey <= 0) {
            continue;
        }
        $serial = trim((string)($row['serial_number'] ?? ''));
        if ($serial !== '') {
            $positionEquipmentSerialSeen[$equipmentIdKey] = $serial;
        }
    }
}

$stmtUsed = mysqli_prepare(
    $conn,
    "SELECT id FROM idf_links
     WHERE port_id_a IN (?, ?) OR port_id_b IN (?, ?)
     LIMIT 1"
);
if ($stmtUsed) {
    mysqli_stmt_bind_param($stmtUsed, 'iiii', $portA, $portB, $portA, $portB);
    mysqli_stmt_execute($stmtUsed);
    $resUsed = mysqli_stmt_get_result($stmtUsed);
    $foundUsed = $resUsed && mysqli_num_rows($resUsed) > 0;
    mysqli_stmt_close($stmtUsed);
    if ($foundUsed) {
        idf_fail('One of the ports is already linked');
    }
}

$label_val = $label !== '' ? $label : null;
$notes_val = $notes !== '' ? $notes : null;
$equipmentId_val = $equipmentId > 0 ? (string)$equipmentId : $equipmentToken;
$equipmentHostname_val = null;
$equipmentPortType_val = null;
$equipmentPort_val = null;
$equipmentVlanId_val = null;
$equipmentRj45SpeedId_val = $rj45SpeedId > 0 ? $rj45SpeedId : null;
$equipmentFiberPortId_val = $fiberPortId > 0 ? $fiberPortId : null;
$equipmentFiberPatchId_val = $fiberPatchId > 0 ? $fiberPatchId : null;
$equipmentFiberRackId_val = $fiberRackId > 0 ? $fiberRackId : null;
$equipmentToIdfId_val = $toIdfId > 0 ? $toIdfId : null;
$equipmentToRackId_val = $toRackId > 0 ? $toRackId : null;
$equipmentToLocationId_val = $toLocationId > 0 ? $toLocationId : null;
$equipmentLabel_val = null;
$equipmentComments_val = null;
$equipmentStatusId_val = null;
$equipmentColorId_val = null;
$equipmentConnectedToLabel = null;
$selectedColorName = null;
$selectedColorHex = null;

if ($cableColorId > 0) {
    $stmtColor = mysqli_prepare(
        $conn,
        "SELECT id, color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND id = ?
         LIMIT 1"
    );
    if ($stmtColor) {
        mysqli_stmt_bind_param($stmtColor, 'ii', $company_id, $cableColorId);
        mysqli_stmt_execute($stmtColor);
        $resColor = mysqli_stmt_get_result($stmtColor);
        $colorRow = $resColor ? mysqli_fetch_assoc($resColor) : null;
        if ($colorRow) {
            $selectedColorName = trim((string)($colorRow['color_name'] ?? ''));
            $selectedColorHex = trim((string)($colorRow['hex_color'] ?? ''));
            if ($selectedColorName === '') { $selectedColorName = null; }
            if ($selectedColorHex === '') { $selectedColorHex = null; }
        }
        mysqli_stmt_close($stmtColor);
    }
}

if (($selectedColorName === null || $selectedColorName === '') && $resolvedColorNameInput !== '') {
    $stmtColorByName = mysqli_prepare(
        $conn,
        "SELECT id, color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND LOWER(color_name) = LOWER(?)
         LIMIT 1"
    );
    if ($stmtColorByName) {
        mysqli_stmt_bind_param($stmtColorByName, 'is', $company_id, $resolvedColorNameInput);
        mysqli_stmt_execute($stmtColorByName);
        $resColorByName = mysqli_stmt_get_result($stmtColorByName);
        $colorByName = $resColorByName ? mysqli_fetch_assoc($resColorByName) : null;
        mysqli_stmt_close($stmtColorByName);
        if ($colorByName) {
            $cableColorId = (int)($colorByName['id'] ?? 0);
            $selectedColorName = trim((string)($colorByName['color_name'] ?? ''));
            $selectedColorHex = trim((string)($colorByName['hex_color'] ?? ''));
        }
    }
}

if (($selectedColorName === null || $selectedColorName === '') && $resolvedColorHexInput !== '') {
    $stmtColorByHex = mysqli_prepare(
        $conn,
        "SELECT id, color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND UPPER(hex_color) = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($stmtColorByHex) {
        mysqli_stmt_bind_param($stmtColorByHex, 'is', $company_id, $resolvedColorHexInput);
        mysqli_stmt_execute($stmtColorByHex);
        $resColorByHex = mysqli_stmt_get_result($stmtColorByHex);
        $colorByHex = $resColorByHex ? mysqli_fetch_assoc($resColorByHex) : null;
        mysqli_stmt_close($stmtColorByHex);
        if ($colorByHex) {
            $cableColorId = (int)($colorByHex['id'] ?? 0);
            $selectedColorName = trim((string)($colorByHex['color_name'] ?? ''));
            $selectedColorHex = trim((string)($colorByHex['hex_color'] ?? ''));
        }
    }
}

if (($selectedColorName === null || $selectedColorName === '') && ($resolvedColorNameInput !== '' || $resolvedColorHexInput !== '')) {
    $colorNameForCreate = $resolvedColorNameInput !== '' ? $resolvedColorNameInput : $resolvedColorHexInput;
    $colorHexForCreate = $resolvedColorHexInput;

    $stmtCreateColor = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO cable_colors (company_id, color_name, hex_color)
         VALUES (?, ?, NULLIF(?, ''))"
    );
    if ($stmtCreateColor) {
        mysqli_stmt_bind_param($stmtCreateColor, 'iss', $company_id, $colorNameForCreate, $colorHexForCreate);
        mysqli_stmt_execute($stmtCreateColor);
        mysqli_stmt_close($stmtCreateColor);
    }

    $stmtReloadColor = mysqli_prepare(
        $conn,
        "SELECT id, color_name, hex_color
         FROM cable_colors
         WHERE company_id = ?
           AND LOWER(color_name) = LOWER(?)
         LIMIT 1"
    );
    if ($stmtReloadColor) {
        mysqli_stmt_bind_param($stmtReloadColor, 'is', $company_id, $colorNameForCreate);
        mysqli_stmt_execute($stmtReloadColor);
        $resReloadColor = mysqli_stmt_get_result($stmtReloadColor);
        $reloadColor = $resReloadColor ? mysqli_fetch_assoc($resReloadColor) : null;
        mysqli_stmt_close($stmtReloadColor);
        if ($reloadColor) {
            $cableColorId = (int)($reloadColor['id'] ?? 0);
            $selectedColorName = trim((string)($reloadColor['color_name'] ?? ''));
            $selectedColorHex = trim((string)($reloadColor['hex_color'] ?? ''));
        }
    }
}

if ($selectedColorName === null || $selectedColorName === '') {
    $selectedColorName = $resolvedColorNameInput !== '' ? $resolvedColorNameInput : 'Gray';
}
if ($selectedColorHex === null || $selectedColorHex === '') {
    $selectedColorHex = $resolvedColorHexInput !== '' ? $resolvedColorHexInput : '#808080';
}
if ($cableColorId <= 0) {
    $stmtFallbackColor = mysqli_prepare(
        $conn,
        "SELECT id
         FROM cable_colors
         WHERE company_id = ?
           AND LOWER(color_name) = LOWER(?)
         LIMIT 1"
    );
    if ($stmtFallbackColor) {
        mysqli_stmt_bind_param($stmtFallbackColor, 'is', $company_id, $selectedColorName);
        mysqli_stmt_execute($stmtFallbackColor);
        $resFallbackColor = mysqli_stmt_get_result($stmtFallbackColor);
        $fallbackColor = $resFallbackColor ? mysqli_fetch_assoc($resFallbackColor) : null;
        mysqli_stmt_close($stmtFallbackColor);
        if ($fallbackColor) {
            $cableColorId = (int)($fallbackColor['id'] ?? 0);
        }
    }
}

if ($switchPortId > 0) {
    $stmtSwitchPort = mysqli_prepare(
        $conn,
        "SELECT
            sp.equipment_id,
            COALESCE(NULLIF(sp.hostname, ''), e.name) AS equipment_hostname,
            e.serial_number AS equipment_serial_number,
            sp.port_type AS equipment_port_type_raw,
            COALESCE(spt.type, sp.port_type) AS equipment_port_type,
            sp.port_number AS equipment_port,
            sp.vlan_id AS equipment_vlan_id,
            sp.rj45_speed_id AS equipment_rj45_speed_id,
            sp.fiber_port_id AS equipment_fiber_port_id,
            sp.fiber_patch_id AS equipment_fiber_patch_id,
            sp.fiber_rack_id AS equipment_fiber_rack_id,
            sp.to_idf_id AS equipment_to_idf_id,
            sp.to_rack_id AS equipment_to_rack_id,
            sp.to_location_id AS equipment_to_location_id,
            sp.{$switchPortLabelColumn} AS equipment_label,
            sp.comments AS equipment_comments,
            sp.status_id AS equipment_status_id,
            sp.color_id AS equipment_color_id
         FROM switch_ports sp
         JOIN equipment e ON e.id = sp.equipment_id
         LEFT JOIN switch_port_types spt ON spt.id = sp.port_type AND spt.company_id = sp.company_id
         WHERE sp.id = ?
           AND sp.company_id = ?
           AND sp.equipment_id = ?
         LIMIT 1"
    );
    if ($stmtSwitchPort) {
        mysqli_stmt_bind_param($stmtSwitchPort, 'iii', $switchPortId, $company_id, $equipmentId);
        mysqli_stmt_execute($stmtSwitchPort);
        $resSwitchPort = mysqli_stmt_get_result($stmtSwitchPort);
        $switchPort = $resSwitchPort ? mysqli_fetch_assoc($resSwitchPort) : null;
        mysqli_stmt_close($stmtSwitchPort);

        if (!$switchPort) {
            idf_fail('Selected equipment port not found');
        }

        $equipmentSwitchPortType = (string)($switchPort['equipment_port_type'] ?? '');
        if (!idf_ports_are_link_compatible($portTypeLabelA, $equipmentSwitchPortType)) {
            idf_fail(idf_port_type_link_mismatch_message($portTypeLabelA, $equipmentSwitchPortType));
        }

        $equipmentId_val = (string)$switchPort['equipment_id'];
        $equipmentHostname_val = (string)$switchPort['equipment_hostname'];
        $equipmentPortType_val = (string)$switchPort['equipment_port_type'];
        $equipmentPort_val = (string)$switchPort['equipment_port'];
        $equipmentConnectedToLabel = trim((string)($switchPort['equipment_serial_number'] ?? ''));
        if ($equipmentConnectedToLabel === '') {
            $equipmentConnectedToLabel = trim((string)$equipmentHostname_val);
        } else {
            $equipmentHostname_val = $equipmentConnectedToLabel;
        }
        $equipmentVlanId_val = isset($switchPort['equipment_vlan_id']) && $switchPort['equipment_vlan_id'] !== null
            ? (int)$switchPort['equipment_vlan_id']
            : null;
        $equipmentRj45SpeedId_val = isset($switchPort['equipment_rj45_speed_id']) && $switchPort['equipment_rj45_speed_id'] !== null
            ? (int)$switchPort['equipment_rj45_speed_id']
            : $equipmentRj45SpeedId_val;
        $equipmentFiberPortId_val = isset($switchPort['equipment_fiber_port_id']) && $switchPort['equipment_fiber_port_id'] !== null
            ? (int)$switchPort['equipment_fiber_port_id']
            : $equipmentFiberPortId_val;
        $equipmentFiberPatchId_val = isset($switchPort['equipment_fiber_patch_id']) && $switchPort['equipment_fiber_patch_id'] !== null
            ? (int)$switchPort['equipment_fiber_patch_id']
            : $equipmentFiberPatchId_val;
        $equipmentFiberRackId_val = isset($switchPort['equipment_fiber_rack_id']) && $switchPort['equipment_fiber_rack_id'] !== null
            ? (int)$switchPort['equipment_fiber_rack_id']
            : $equipmentFiberRackId_val;
        $equipmentToIdfId_val = isset($switchPort['equipment_to_idf_id']) && $switchPort['equipment_to_idf_id'] !== null
            ? (int)$switchPort['equipment_to_idf_id']
            : $equipmentToIdfId_val;
        $equipmentToRackId_val = isset($switchPort['equipment_to_rack_id']) && $switchPort['equipment_to_rack_id'] !== null
            ? (int)$switchPort['equipment_to_rack_id']
            : $equipmentToRackId_val;
        $equipmentToLocationId_val = isset($switchPort['equipment_to_location_id']) && $switchPort['equipment_to_location_id'] !== null
            ? (int)$switchPort['equipment_to_location_id']
            : $equipmentToLocationId_val;
        $equipmentLabel_val = (string)$switchPort['equipment_label'];
        $equipmentComments_val = (string)$switchPort['equipment_comments'];
        $equipmentStatusId_val = isset($switchPort['equipment_status_id']) && $switchPort['equipment_status_id'] !== null
            ? (int)$switchPort['equipment_status_id']
            : null;
        $equipmentColorId_val = isset($switchPort['equipment_color_id']) && $switchPort['equipment_color_id'] !== null
            ? (int)$switchPort['equipment_color_id']
            : null;
    }
} elseif ($equipmentId > 0) {
    $stmtEquipment = mysqli_prepare(
        $conn,
        "SELECT e.id, e.name, e.serial_number
         FROM equipment e
         WHERE e.id = ?
           AND e.company_id = ?
         LIMIT 1"
    );
    if ($stmtEquipment) {
        mysqli_stmt_bind_param($stmtEquipment, 'ii', $equipmentId, $company_id);
        mysqli_stmt_execute($stmtEquipment);
        $resEquipment = mysqli_stmt_get_result($stmtEquipment);
        $equipment = $resEquipment ? mysqli_fetch_assoc($resEquipment) : null;
        mysqli_stmt_close($stmtEquipment);

        if (!$equipment) {
            idf_fail('Selected equipment not found');
        }
        $equipmentId_val = (string)$equipment['id'];
        $equipmentHostname_val = (string)$equipment['name'];
        $equipmentConnectedToLabel = trim((string)($equipment['serial_number'] ?? ''));
        if ($equipmentConnectedToLabel === '') {
            $equipmentConnectedToLabel = trim((string)$equipmentHostname_val);
        } else {
            $equipmentHostname_val = $equipmentConnectedToLabel;
        }
    }
}

if (
    $switchPortId > 0
    && $equipmentId_val !== null && trim((string)$equipmentId_val) !== ''
    && $equipmentPortType_val !== null && trim((string)$equipmentPortType_val) !== ''
    && $equipmentPort_val !== null && trim((string)$equipmentPort_val) !== ''
) {
    $stmtExistingEquipmentPortLink = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_links
         WHERE company_id = ?
           AND equipment_id = ?
           AND CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(equipment_port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(UPPER(REPLACE(REPLACE(TRIM(?), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           AND equipment_port = ?
         LIMIT 1"
    );
    if ($stmtExistingEquipmentPortLink) {
        $equipmentIdCheck = trim((string)$equipmentId_val);
        $equipmentPortTypeCheck = trim((string)$equipmentPortType_val);
        $equipmentPortCheck = trim((string)$equipmentPort_val);
        mysqli_stmt_bind_param(
            $stmtExistingEquipmentPortLink,
            'isss',
            $company_id,
            $equipmentIdCheck,
            $equipmentPortTypeCheck,
            $equipmentPortCheck
        );
        mysqli_stmt_execute($stmtExistingEquipmentPortLink);
        $resExistingEquipmentPortLink = mysqli_stmt_get_result($stmtExistingEquipmentPortLink);
        $hasExistingEquipmentPortLink = $resExistingEquipmentPortLink && mysqli_num_rows($resExistingEquipmentPortLink) > 0;
        mysqli_stmt_close($stmtExistingEquipmentPortLink);
        if ($hasExistingEquipmentPortLink) {
            idf_fail('Selected equipment port is already linked to another IDF port.');
        }
    }
}

if ($switchPortId > 0) {
    $newPortNumber = null;
    if ($linkedDestinationPort !== '' && ctype_digit($linkedDestinationPort)) {
        $newPortNumber = (int)$linkedDestinationPort;
    } elseif ($linkedEquipmentPort !== '' && ctype_digit($linkedEquipmentPort)) {
        $newPortNumber = (int)$linkedEquipmentPort;
    }

    $switchLabel = $label !== '' ? $label : null;
    $switchComments = $notes !== '' ? $notes : null;

    $switchColorId = $cableColorId > 0 ? $cableColorId : null;

    $updates = [
        "{$switchPortLabelColumn} = ?",
        "comments = ?",
        "status_id = NULLIF(?, 0)",
    ];
    $types = 'ssi';
    $params = [$switchLabel, $switchComments, $status_id];

    if ($switchColorId !== null) {
        $updates[] = "color_id = ?";
        $types .= 'i';
        $params[] = $switchColorId;
    }
    $canUpdatePortNumber = false;
    if ($newPortNumber !== null && $newPortNumber > 0) {
        $currentPortNumber = isset($switchPort['equipment_port']) ? (int)$switchPort['equipment_port'] : 0;
        if ($newPortNumber === $currentPortNumber) {
            $canUpdatePortNumber = true;
        } else {
            $stmtPortConflict = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM switch_ports
                 WHERE company_id = ?
                   AND equipment_id = ?
                   AND port_type = ?
                   AND port_number = ?
                   AND id <> ?
                 LIMIT 1"
            );
            if ($stmtPortConflict) {
                $portTypeRaw = (string)($switchPort['equipment_port_type_raw'] ?? '');
                mysqli_stmt_bind_param($stmtPortConflict, 'iisii', $company_id, $equipmentId, $portTypeRaw, $newPortNumber, $switchPortId);
                mysqli_stmt_execute($stmtPortConflict);
                $resPortConflict = mysqli_stmt_get_result($stmtPortConflict);
                $hasConflict = $resPortConflict && mysqli_num_rows($resPortConflict) > 0;
                mysqli_stmt_close($stmtPortConflict);
                $canUpdatePortNumber = !$hasConflict;
            }
        }
    }
    if ($canUpdatePortNumber) {
        $updates[] = "port_number = ?";
        $types .= 'i';
        $params[] = $newPortNumber;
    }

    $sqlUpd = "UPDATE switch_ports SET " . implode(', ', $updates) . " WHERE id = ? AND company_id = ? AND equipment_id = ? LIMIT 1";
    $types .= 'iii';
    $params[] = $switchPortId;
    $params[] = $company_id;
    $params[] = $equipmentId;

    $stmtUpd = mysqli_prepare($conn, $sqlUpd);
    if ($stmtUpd) {
        mysqli_stmt_bind_param($stmtUpd, $types, ...$params);
        try {
            if (!mysqli_stmt_execute($stmtUpd)) {
                idf_fail('DB error updating switch port: ' . mysqli_stmt_error($stmtUpd), 500);
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                idf_fail('Selected destination port number conflicts with an existing switch port on this equipment.');
            }
            throw $e;
        }
        mysqli_stmt_close($stmtUpd);
    }
}

if (
    $switchPortId <= 0
    && ($equipmentHostname_val === null || trim((string)$equipmentHostname_val) === '')
    && isset($positionEquipmentSeen[$portB])
) {
    $destinationEquipmentIdRaw = trim((string)($positionEquipmentSeen[$portB] ?? ''));
    $destinationEquipmentId = ctype_digit($destinationEquipmentIdRaw) ? (int)$destinationEquipmentIdRaw : 0;
    $destinationPortNo = (int)($portNoSeen[$portB] ?? 0);
    if ($destinationEquipmentId > 0 && $destinationPortNo > 0) {
        $stmtDestinationSwitch = mysqli_prepare(
            $conn,
            "SELECT
                COALESCE(NULLIF(sp.hostname, ''), e.hostname, e.name) AS equipment_hostname,
                COALESCE(spt.type, sp.port_type) AS equipment_port_type,
                sp.port_number AS equipment_port,
                sp.vlan_id AS equipment_vlan_id,
                sp.rj45_speed_id AS equipment_rj45_speed_id,
                sp.fiber_port_id AS equipment_fiber_port_id,
                sp.fiber_patch_id AS equipment_fiber_patch_id,
                sp.fiber_rack_id AS equipment_fiber_rack_id,
                sp.to_idf_id AS equipment_to_idf_id,
                sp.to_rack_id AS equipment_to_rack_id,
                sp.to_location_id AS equipment_to_location_id,
                sp.{$switchPortLabelColumn} AS equipment_label,
                sp.comments AS equipment_comments,
                sp.status_id AS equipment_status_id,
                sp.color_id AS equipment_color_id
             FROM switch_ports sp
             JOIN equipment e ON e.id = sp.equipment_id
             LEFT JOIN switch_port_types spt ON spt.id = sp.port_type AND spt.company_id = sp.company_id
             WHERE sp.company_id = ?
               AND sp.equipment_id = ?
               AND sp.port_number = ?
             ORDER BY sp.id ASC
             LIMIT 1"
        );
        if ($stmtDestinationSwitch) {
            mysqli_stmt_bind_param($stmtDestinationSwitch, 'iii', $company_id, $destinationEquipmentId, $destinationPortNo);
            mysqli_stmt_execute($stmtDestinationSwitch);
            $resDestinationSwitch = mysqli_stmt_get_result($stmtDestinationSwitch);
            $destinationSwitch = $resDestinationSwitch ? mysqli_fetch_assoc($resDestinationSwitch) : null;
            mysqli_stmt_close($stmtDestinationSwitch);

            if ($destinationSwitch) {
                $equipmentId_val = (string)$destinationEquipmentId;
                $equipmentHostname_val = trim((string)($destinationSwitch['equipment_hostname'] ?? ''));
                $equipmentPortType_val = (string)($destinationSwitch['equipment_port_type'] ?? '');
                $equipmentPort_val = (string)($destinationSwitch['equipment_port'] ?? '');
                $equipmentVlanId_val = isset($destinationSwitch['equipment_vlan_id']) ? (int)$destinationSwitch['equipment_vlan_id'] : null;
                $equipmentRj45SpeedId_val = isset($destinationSwitch['equipment_rj45_speed_id']) ? (int)$destinationSwitch['equipment_rj45_speed_id'] : $equipmentRj45SpeedId_val;
                $equipmentFiberPortId_val = isset($destinationSwitch['equipment_fiber_port_id']) ? (int)$destinationSwitch['equipment_fiber_port_id'] : $equipmentFiberPortId_val;
                $equipmentFiberPatchId_val = isset($destinationSwitch['equipment_fiber_patch_id']) ? (int)$destinationSwitch['equipment_fiber_patch_id'] : $equipmentFiberPatchId_val;
                $equipmentFiberRackId_val = isset($destinationSwitch['equipment_fiber_rack_id']) ? (int)$destinationSwitch['equipment_fiber_rack_id'] : $equipmentFiberRackId_val;
                $equipmentToIdfId_val = isset($destinationSwitch['equipment_to_idf_id']) ? (int)$destinationSwitch['equipment_to_idf_id'] : $equipmentToIdfId_val;
                $equipmentToRackId_val = isset($destinationSwitch['equipment_to_rack_id']) ? (int)$destinationSwitch['equipment_to_rack_id'] : $equipmentToRackId_val;
                $equipmentToLocationId_val = isset($destinationSwitch['equipment_to_location_id']) ? (int)$destinationSwitch['equipment_to_location_id'] : $equipmentToLocationId_val;
                $equipmentLabel_val = (string)($destinationSwitch['equipment_label'] ?? '');
                $equipmentComments_val = (string)($destinationSwitch['equipment_comments'] ?? '');
                $equipmentStatusId_val = isset($destinationSwitch['equipment_status_id']) ? (int)$destinationSwitch['equipment_status_id'] : null;
                $equipmentColorId_val = isset($destinationSwitch['equipment_color_id']) ? (int)$destinationSwitch['equipment_color_id'] : null;
            }
        }
    }
}

$statusSyncId = (int)$status_id;
if ($statusSyncId <= 0 && $equipmentStatusId_val !== null && (int)$equipmentStatusId_val > 0) {
    $statusSyncId = (int)$equipmentStatusId_val;
}
if ($statusSyncId <= 0) {
    $statusSyncId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
}

if ($requestVlanId > 0) {
    $stmtVlanScope = mysqli_prepare(
        $conn,
        'SELECT id FROM vlans WHERE company_id = ? AND id = ? LIMIT 1'
    );
    if ($stmtVlanScope) {
        mysqli_stmt_bind_param($stmtVlanScope, 'ii', $company_id, $requestVlanId);
        mysqli_stmt_execute($stmtVlanScope);
        $resVlanScope = mysqli_stmt_get_result($stmtVlanScope);
        $vlanScopeRow = $resVlanScope ? mysqli_fetch_assoc($resVlanScope) : null;
        mysqli_stmt_close($stmtVlanScope);
        if ($vlanScopeRow) {
            $equipmentVlanId_val = $requestVlanId;
        }
    }
}

$poeSyncId = 0;
if ($poeId > 0) {
    $stmtPoeScope = mysqli_prepare(
        $conn,
        'SELECT id FROM equipment_poe WHERE company_id = ? AND id = ? LIMIT 1'
    );
    if ($stmtPoeScope) {
        mysqli_stmt_bind_param($stmtPoeScope, 'ii', $company_id, $poeId);
        mysqli_stmt_execute($stmtPoeScope);
        $resPoeScope = mysqli_stmt_get_result($stmtPoeScope);
        $poeScopeRow = $resPoeScope ? mysqli_fetch_assoc($resPoeScope) : null;
        mysqli_stmt_close($stmtPoeScope);
        if ($poeScopeRow) {
            $poeSyncId = $poeId;
        }
    }
}

$stmtFinal = mysqli_prepare(
    $conn,
    "INSERT INTO idf_links (
        company_id, port_id_a, port_id_b, equipment_id, equipment_hostname,
        equipment_port_type, equipment_port, equipment_vlan_id, equipment_rj45_speed_id,
        equipment_fiber_port_id, equipment_fiber_patch_id, equipment_fiber_rack_id,
        equipment_to_idf_id, equipment_to_rack_id, equipment_to_location_id, equipment_label,
        equipment_comments, equipment_status_id, equipment_color_id, cable_color_id,
        cable_color_hex, cable_label, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?)"
);

$insertedLinkIds = [];
if ($stmtFinal) {
    $pairs = [
        [$portA, $portB],
        [$portB, $portA],
    ];

    foreach ($pairs as $pair) {
        $portIdA = (int)$pair[0];
        $portIdB = (int)$pair[1];
        $equipmentIdInsert = $equipmentId_val;
        if ($equipmentIdInsert === null || $equipmentIdInsert === '') {
            $equipmentIdInsert = trim((string)($positionEquipmentSeen[$portIdB] ?? ''));
        }
        if ($equipmentIdInsert === '') {
            $equipmentIdInsert = sprintf('%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
        }
        mysqli_stmt_bind_param(
            $stmtFinal, 'iiissssiiiiiiiissiiisss',
            $company_id, $portIdA, $portIdB, $equipmentIdInsert, $equipmentHostname_val,
            $equipmentPortType_val, $equipmentPort_val, $equipmentVlanId_val, $equipmentRj45SpeedId_val,
            $equipmentFiberPortId_val, $equipmentFiberPatchId_val, $equipmentFiberRackId_val,
            $equipmentToIdfId_val, $equipmentToRackId_val, $equipmentToLocationId_val, $equipmentLabel_val,
            $equipmentComments_val, $statusSyncId, $equipmentColorId_val, $cableColorId,
            $selectedColorHex, $label_val, $notes_val
        );
        if (!mysqli_stmt_execute($stmtFinal)) {
            idf_fail('DB error creating link: ' . mysqli_stmt_error($stmtFinal), 500);
        }
        $insertedLinkIds[] = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtFinal);
}

if (
    isset($positionNoSeen[$portA], $positionNoSeen[$portB], $portNoSeen[$portA], $portNoSeen[$portB], $deviceSeen[$portA], $deviceSeen[$portB])
) {
    $connectedDeviceB = trim((string)$deviceSeen[$portB]);
    $connectedDeviceA = trim((string)$deviceSeen[$portA]);
    $positionEquipmentB = ctype_digit(trim((string)($positionEquipmentSeen[$portB] ?? '')))
        ? (int)$positionEquipmentSeen[$portB]
        : 0;
    $positionEquipmentA = ctype_digit(trim((string)($positionEquipmentSeen[$portA] ?? '')))
        ? (int)$positionEquipmentSeen[$portA]
        : 0;
    if ($positionEquipmentB > 0 && isset($positionEquipmentSerialSeen[$positionEquipmentB])) {
        $connectedDeviceB = $positionEquipmentSerialSeen[$positionEquipmentB];
    }
    if ($positionEquipmentA > 0 && isset($positionEquipmentSerialSeen[$positionEquipmentA])) {
        $connectedDeviceA = $positionEquipmentSerialSeen[$positionEquipmentA];
    }
    if (is_string($equipmentConnectedToLabel)) {
        $equipmentConnectedToLabel = trim($equipmentConnectedToLabel);
        if ($equipmentConnectedToLabel !== '') {
            $connectedDeviceB = $equipmentConnectedToLabel;
            $connectedDeviceA = $equipmentConnectedToLabel;
        }
    }
    $connectedToA = 'Pos ' . (int)$positionNoSeen[$portB] . ' • ' . $connectedDeviceB . ' • Port ' . (int)$portNoSeen[$portB];
    $connectedToB = 'Pos ' . (int)$positionNoSeen[$portA] . ' • ' . $connectedDeviceA . ' • Port ' . (int)$portNoSeen[$portA];

    $labelSync = trim((string)($equipmentLabel_val ?? ''));
    if ($labelSync === '' || $labelSync === '0') {
        $labelSync = trim((string)($label_val ?? ''));
    }
    if ($labelSync === '' || $labelSync === '0') {
        $labelSync = null;
    }

    $notesSync = trim((string)($equipmentComments_val ?? ''));
    if ($notesSync === '') {
        $notesSync = trim((string)($notes_val ?? ''));
    }
    if ($notesSync === '') {
        $notesSync = null;
    }

    $stmtUpdatePortSql = "UPDATE idf_ports SET connected_to = ?, status_id = ?, cable_color = ?, hex_color = ?";
    if ($equipmentVlanId_val !== null && $equipmentVlanId_val > 0) {
        $stmtUpdatePortSql .= ", vlan_id = ?";
    }
    if ($poeSyncId > 0) {
        $stmtUpdatePortSql .= ", poe_id = ?";
    }
    if ($labelSync !== null) {
        $stmtUpdatePortSql .= ", label = ?";
    }
    if ($notesSync !== null) {
        $stmtUpdatePortSql .= ", notes = ?";
    }
    $stmtUpdatePortSql .= " WHERE id = ? LIMIT 1";
    $stmtUpdatePort = mysqli_prepare($conn, $stmtUpdatePortSql);
    if ($stmtUpdatePort) {
        $updateTypes = 'siss';
        $updateValuesA = [$connectedToA, $statusSyncId, $selectedColorName, $selectedColorHex];
        $updateValuesB = [$connectedToB, $statusSyncId, $selectedColorName, $selectedColorHex];
        if ($equipmentVlanId_val !== null && $equipmentVlanId_val > 0) {
            $updateTypes .= 'i';
            $vlanSyncId = (int)$equipmentVlanId_val;
            $updateValuesA[] = $vlanSyncId;
            $updateValuesB[] = $vlanSyncId;
        }
        if ($poeSyncId > 0) {
            $updateTypes .= 'i';
            $updateValuesA[] = $poeSyncId;
            $updateValuesB[] = $poeSyncId;
        }
        if ($labelSync !== null) {
            $updateTypes .= 's';
            $updateValuesA[] = $labelSync;
            $updateValuesB[] = $labelSync;
        }
        if ($notesSync !== null) {
            $updateTypes .= 's';
            $updateValuesA[] = $notesSync;
            $updateValuesB[] = $notesSync;
        }
        $updateTypes .= 'i';
        $updateValuesA[] = $portA;
        $updateValuesB[] = $portB;

        mysqli_stmt_bind_param($stmtUpdatePort, $updateTypes, ...$updateValuesA);
        mysqli_stmt_execute($stmtUpdatePort);
        mysqli_stmt_bind_param($stmtUpdatePort, $updateTypes, ...$updateValuesB);
        mysqli_stmt_execute($stmtUpdatePort);
        mysqli_stmt_close($stmtUpdatePort);
    }

    $vlanForSwitchSync = ($equipmentVlanId_val !== null && (int)$equipmentVlanId_val > 0)
        ? (int)$equipmentVlanId_val
        : 0;

    $switchSetSql = "sp.{$switchPortLabelColumn} = ?,
             sp.status_id = NULLIF(?, 0),
             sp.color_id = NULLIF(?, 0),
             sp.comments = ?";
    if ($vlanForSwitchSync > 0) {
        $switchSetSql .= ",
             sp.vlan_id = NULLIF(?, 0)";
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
         LEFT JOIN switch_port_types spt
           ON spt.id = pr.port_type
          AND spt.company_id = pr.company_id
         LEFT JOIN switch_port_types spt_any
           ON spt_any.id = pr.port_type
         SET {$switchSetSql}
         WHERE sp.company_id = ?
           AND p.company_id = sp.company_id
           AND CONVERT(CAST(p.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(CAST(sp.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           AND sp.port_number = pr.port_no
           AND (
                CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(COALESCE(spt.type, spt_any.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = spt.id
                )
                OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, spt_any.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           )
        "
    );
    if ($stmtSwitchSync) {
        $switchColorSyncId = $cableColorId > 0 ? $cableColorId : 0;
        $switchLabelSync = $labelSync ?? $label_val;
        $switchNoteSync = $notesSync ?? $notes_val;
        if ($vlanForSwitchSync > 0) {
            mysqli_stmt_bind_param(
                $stmtSwitchSync,
                'isiiisi',
                $portA,
                $switchLabelSync,
                $statusSyncId,
                $switchColorSyncId,
                $switchNoteSync,
                $vlanForSwitchSync,
                $company_id
            );
        } else {
            mysqli_stmt_bind_param(
                $stmtSwitchSync,
                'isiisi',
                $portA,
                $switchLabelSync,
                $statusSyncId,
                $switchColorSyncId,
                $switchNoteSync,
                $company_id
            );
        }
        if (!mysqli_stmt_execute($stmtSwitchSync)) {
            idf_fail('DB error syncing source switch port: ' . mysqli_stmt_error($stmtSwitchSync), 500);
        }
        if ($vlanForSwitchSync > 0) {
            mysqli_stmt_bind_param(
                $stmtSwitchSync,
                'isiiisi',
                $portB,
                $switchLabelSync,
                $statusSyncId,
                $switchColorSyncId,
                $switchNoteSync,
                $vlanForSwitchSync,
                $company_id
            );
        } else {
            mysqli_stmt_bind_param(
                $stmtSwitchSync,
                'isiisi',
                $portB,
                $switchLabelSync,
                $statusSyncId,
                $switchColorSyncId,
                $switchNoteSync,
                $company_id
            );
        }
        if (!mysqli_stmt_execute($stmtSwitchSync)) {
            idf_fail('DB error syncing destination switch port: ' . mysqli_stmt_error($stmtSwitchSync), 500);
        }
        mysqli_stmt_close($stmtSwitchSync);
    }
}

idf_ok([
    'link_id' => (int)($insertedLinkIds[0] ?? 0),
    'link_ids' => $insertedLinkIds,
]);
