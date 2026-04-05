<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$portA = (int)($data['port_id_a'] ?? 0);
$portB = (int)($data['port_id_b'] ?? 0);
$equipmentId = isset($data['equipment_id']) && $data['equipment_id'] !== null ? (int)$data['equipment_id'] : 0;
$switchPortId = isset($data['switch_port_id']) && $data['switch_port_id'] !== null ? (int)$data['switch_port_id'] : 0;
$color = trim((string)($data['cable_color'] ?? 'yellow'));
$label = trim((string)($data['cable_label'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$linkedEquipmentPort = trim((string)($data['linked_equipment_port'] ?? ''));
$linkedDestinationPort = trim((string)($data['linked_destination_port'] ?? ''));

if ($portA <= 0 || $portB <= 0) {
    idf_fail('Invalid port ids');
}
if ($portA === $portB) {
    idf_fail('Cannot link a port to itself');
}
if ($color === '') {
    $color = 'yellow';
}
if ($switchPortId > 0 && $equipmentId <= 0) {
    idf_fail('Equipment is required when selecting an equipment port');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT pr.id AS port_id, pr.port_no, i.company_id, p.id AS position_id, p.position_no, p.device_name
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id IN (?, ?)"
);

$seen = [];
$positionSeen = [];
$positionNoSeen = [];
$portNoSeen = [];
$deviceSeen = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $portA, $portB);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $seen[(int)$r['port_id']] = (int)$r['company_id'];
        $positionSeen[(int)$r['port_id']] = (int)$r['position_id'];
        $positionNoSeen[(int)$r['port_id']] = (int)$r['position_no'];
        $portNoSeen[(int)$r['port_id']] = (int)$r['port_no'];
        $deviceSeen[(int)$r['port_id']] = (string)($r['device_name'] ?? '');
    }
    mysqli_stmt_close($stmt);
}

if (count($seen) !== 2) {
    idf_fail('Port not found', 404);
}
if (($seen[$portA] ?? null) !== $company_id || ($seen[$portB] ?? null) !== $company_id) {
    idf_fail('Forbidden', 403);
}
if (($positionSeen[$portA] ?? 0) === ($positionSeen[$portB] ?? 0)) {
    $deviceName = trim((string)($deviceSeen[$portA] ?? 'this device'));
    idf_fail('Cannot link two ports on the same device (' . $deviceName . '). Choose a port from another device to avoid switching loops.');
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
$equipmentId_val = $equipmentId > 0 ? $equipmentId : null;
$equipmentHostname_val = null;
$equipmentPortType_val = null;
$equipmentPort_val = null;
$equipmentVlanId_val = null;
$equipmentLabel_val = null;
$equipmentComments_val = null;
$equipmentStatusId_val = null;
$equipmentColorId_val = null;

if ($switchPortId > 0) {
    $stmtSwitchPort = mysqli_prepare(
        $conn,
        "SELECT
            sp.equipment_id,
            COALESCE(NULLIF(sp.hostname, ''), e.name) AS equipment_hostname,
            sp.port_type AS equipment_port_type,
            sp.port_number AS equipment_port,
            sp.vlan_id AS equipment_vlan_id,
            sp.label AS equipment_label,
            sp.comments AS equipment_comments,
            sp.status_id AS equipment_status_id,
            sp.color_id AS equipment_color_id
         FROM switch_ports sp
         JOIN equipment e ON e.id = sp.equipment_id
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

        $equipmentId_val = (int)$switchPort['equipment_id'];
        $equipmentHostname_val = (string)$switchPort['equipment_hostname'];
        $equipmentPortType_val = (string)$switchPort['equipment_port_type'];
        $equipmentPort_val = (string)$switchPort['equipment_port'];
        $equipmentVlanId_val = isset($switchPort['equipment_vlan_id']) && $switchPort['equipment_vlan_id'] !== null
            ? (int)$switchPort['equipment_vlan_id']
            : null;
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
        "SELECT e.id, e.name
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
        $equipmentId_val = (int)$equipment['id'];
        $equipmentHostname_val = (string)$equipment['name'];
    }
}

if ($switchPortId > 0) {
    $newPortNumber = null;
    if ($linkedEquipmentPort !== '' && ctype_digit($linkedEquipmentPort)) {
        $newPortNumber = (int)$linkedEquipmentPort;
    } elseif ($linkedDestinationPort !== '' && ctype_digit($linkedDestinationPort)) {
        $newPortNumber = (int)$linkedDestinationPort;
    }

    $switchLabel = $label !== '' ? $label : null;
    $switchComments = $notes !== '' ? $notes : null;

    $switchColorId = null;
    $stmtColor = mysqli_prepare(
        $conn,
        "SELECT id
         FROM switch_cablecolors
         WHERE company_id = ?
           AND LOWER(color) = LOWER(?)
         LIMIT 1"
    );
    if ($stmtColor) {
        mysqli_stmt_bind_param($stmtColor, 'is', $company_id, $color);
        mysqli_stmt_execute($stmtColor);
        $resColor = mysqli_stmt_get_result($stmtColor);
        $colorRow = $resColor ? mysqli_fetch_assoc($resColor) : null;
        $switchColorId = $colorRow ? (int)$colorRow['id'] : null;
        mysqli_stmt_close($stmtColor);
    }

    $updates = [
        "label = ?",
        "comments = ?",
    ];
    $types = 'ss';
    $params = [$switchLabel, $switchComments];

    if ($switchColorId !== null) {
        $updates[] = "color_id = ?";
        $types .= 'i';
        $params[] = $switchColorId;
    }
    if ($newPortNumber !== null && $newPortNumber > 0) {
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
        if (!mysqli_stmt_execute($stmtUpd)) {
            idf_fail('DB error updating switch port: ' . mysqli_stmt_error($stmtUpd), 500);
        }
        mysqli_stmt_close($stmtUpd);
    }
}

$stmtFinal = mysqli_prepare(
    $conn,
    "INSERT INTO idf_links (
        company_id, port_id_a, port_id_b, equipment_id, equipment_hostname,
        equipment_port_type, equipment_port, equipment_vlan_id, equipment_label,
        equipment_comments, equipment_status_id, equipment_color_id, cable_color,
        cable_label, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if ($stmtFinal) {
    mysqli_stmt_bind_param(
        $stmtFinal, 'iiiisssissiisss',
        $company_id, $portA, $portB, $equipmentId_val, $equipmentHostname_val,
        $equipmentPortType_val, $equipmentPort_val, $equipmentVlanId_val, $equipmentLabel_val,
        $equipmentComments_val, $equipmentStatusId_val, $equipmentColorId_val, $color,
        $label_val, $notes_val
    );
    if (!mysqli_stmt_execute($stmtFinal)) {
        idf_fail('DB error creating link: ' . mysqli_stmt_error($stmtFinal), 500);
    }
    mysqli_stmt_close($stmtFinal);
}

if (
    isset($positionNoSeen[$portA], $positionNoSeen[$portB], $portNoSeen[$portA], $portNoSeen[$portB], $deviceSeen[$portA], $deviceSeen[$portB])
) {
    $connectedToA = 'Pos ' . (int)$positionNoSeen[$portB] . ' • ' . trim((string)$deviceSeen[$portB]) . ' • Port ' . (int)$portNoSeen[$portB];
    $connectedToB = 'Pos ' . (int)$positionNoSeen[$portA] . ' • ' . trim((string)$deviceSeen[$portA]) . ' • Port ' . (int)$portNoSeen[$portA];

    $stmtUpdatePort = mysqli_prepare($conn, "UPDATE idf_ports SET connected_to = ? WHERE id = ? LIMIT 1");
    if ($stmtUpdatePort) {
        mysqli_stmt_bind_param($stmtUpdatePort, 'si', $connectedToA, $portA);
        mysqli_stmt_execute($stmtUpdatePort);
        mysqli_stmt_bind_param($stmtUpdatePort, 'si', $connectedToB, $portB);
        mysqli_stmt_execute($stmtUpdatePort);
        mysqli_stmt_close($stmtUpdatePort);
    }
}

idf_ok(['link_id' => (int)mysqli_insert_id($conn)]);
