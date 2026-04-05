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

$low = min($portA, $portB);
$high = max($portA, $portB);

$res = mysqli_query(
    $conn,
    "SELECT pr.id AS port_id, i.company_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id IN ($low,$high)"
);

$seen = [];
while ($res && ($r = mysqli_fetch_assoc($res))) {
    $seen[(int)$r['port_id']] = (int)$r['company_id'];
}

if (count($seen) !== 2) {
    idf_fail('Port not found', 404);
}
if ($seen[$low] !== $company_id || $seen[$high] !== $company_id) {
    idf_fail('Forbidden', 403);
}

$resUsed = mysqli_query(
    $conn,
    "SELECT id FROM idf_links
     WHERE port_id_a IN ($low,$high) OR port_id_b IN ($low,$high)
     LIMIT 1"
);
if ($resUsed && mysqli_num_rows($resUsed) > 0) {
    idf_fail('One of the ports is already linked');
}

$colorSql = "'" . idf_escape($conn, $color) . "'";
$labelSql = $label !== '' ? ("'" . idf_escape($conn, $label) . "'") : 'NULL';
$notesSql = $notes !== '' ? ("'" . idf_escape($conn, $notes) . "'") : 'NULL';
$equipmentIdSql = $equipmentId > 0 ? (string)$equipmentId : 'NULL';
$equipmentHostnameSql = 'NULL';
$equipmentPortTypeSql = 'NULL';
$equipmentPortSql = 'NULL';
$equipmentVlanIdSql = 'NULL';
$equipmentLabelSql = 'NULL';
$equipmentCommentsSql = 'NULL';
$equipmentStatusIdSql = 'NULL';
$equipmentColorIdSql = 'NULL';

if ($switchPortId > 0) {
    $resSwitchPort = mysqli_query(
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
         WHERE sp.id = $switchPortId
           AND sp.company_id = $company_id
           AND sp.equipment_id = $equipmentId
         LIMIT 1"
    );
    $switchPort = $resSwitchPort ? mysqli_fetch_assoc($resSwitchPort) : null;
    if (!$switchPort) {
        idf_fail('Selected equipment port not found');
    }

    $equipmentIdSql = (string)((int)$switchPort['equipment_id']);
    $equipmentHostnameSql = "'" . idf_escape($conn, (string)$switchPort['equipment_hostname']) . "'";
    $equipmentPortTypeSql = "'" . idf_escape($conn, (string)$switchPort['equipment_port_type']) . "'";
    $equipmentPortSql = "'" . idf_escape($conn, (string)$switchPort['equipment_port']) . "'";
    $equipmentVlanIdSql = isset($switchPort['equipment_vlan_id']) && $switchPort['equipment_vlan_id'] !== null
        ? (string)((int)$switchPort['equipment_vlan_id'])
        : 'NULL';
    $equipmentLabelSql = "'" . idf_escape($conn, (string)$switchPort['equipment_label']) . "'";
    $equipmentCommentsSql = "'" . idf_escape($conn, (string)$switchPort['equipment_comments']) . "'";
    $equipmentStatusIdSql = isset($switchPort['equipment_status_id']) && $switchPort['equipment_status_id'] !== null
        ? (string)((int)$switchPort['equipment_status_id'])
        : 'NULL';
    $equipmentColorIdSql = isset($switchPort['equipment_color_id']) && $switchPort['equipment_color_id'] !== null
        ? (string)((int)$switchPort['equipment_color_id'])
        : 'NULL';
} elseif ($equipmentId > 0) {
    $resEquipment = mysqli_query(
        $conn,
        "SELECT e.id, e.name
         FROM equipment e
         WHERE e.id = $equipmentId
           AND e.company_id = $company_id
         LIMIT 1"
    );
    $equipment = $resEquipment ? mysqli_fetch_assoc($resEquipment) : null;
    if (!$equipment) {
        idf_fail('Selected equipment not found');
    }
    $equipmentIdSql = (string)((int)$equipment['id']);
    $equipmentHostnameSql = "'" . idf_escape($conn, (string)$equipment['name']) . "'";
}

if ($switchPortId > 0) {
    $newPortNumber = null;
    if ($linkedEquipmentPort !== '' && ctype_digit($linkedEquipmentPort)) {
        $newPortNumber = (int)$linkedEquipmentPort;
    } elseif ($linkedDestinationPort !== '' && ctype_digit($linkedDestinationPort)) {
        $newPortNumber = (int)$linkedDestinationPort;
    }

    $switchLabel = $label !== '' ? ("'" . idf_escape($conn, $label) . "'") : 'NULL';
    $switchComments = $notes !== '' ? ("'" . idf_escape($conn, $notes) . "'") : 'NULL';

    $colorLookup = mysqli_query(
        $conn,
        "SELECT id
         FROM switch_cablecolors
         WHERE company_id = $company_id
           AND LOWER(color) = LOWER('" . idf_escape($conn, $color) . "')
         LIMIT 1"
    );
    $colorRow = $colorLookup ? mysqli_fetch_assoc($colorLookup) : null;
    $switchColorIdSql = $colorRow ? (string)((int)$colorRow['id']) : null;

    $updates = [
        "label = $switchLabel",
        "comments = $switchComments",
    ];
    if ($switchColorIdSql !== null) {
        $updates[] = "color_id = $switchColorIdSql";
    }
    if ($newPortNumber !== null && $newPortNumber > 0) {
        $updates[] = "port_number = $newPortNumber";
    }

    if (!mysqli_query(
        $conn,
        "UPDATE switch_ports
         SET " . implode(', ', $updates) . "
         WHERE id = $switchPortId
           AND company_id = $company_id
           AND equipment_id = $equipmentId
         LIMIT 1"
    )) {
        idf_fail('DB error updating switch port: ' . mysqli_error($conn), 500);
    }
}

if (!mysqli_query(
    $conn,
    "INSERT INTO idf_links (
        company_id,
        port_id_a,
        port_id_b,
        equipment_id,
        equipment_hostname,
        equipment_port_type,
        equipment_port,
        equipment_vlan_id,
        equipment_label,
        equipment_comments,
        equipment_status_id,
        equipment_color_id,
        cable_color,
        cable_label,
        notes
    ) VALUES (
        $company_id,
        $low,
        $high,
        $equipmentIdSql,
        $equipmentHostnameSql,
        $equipmentPortTypeSql,
        $equipmentPortSql,
        $equipmentVlanIdSql,
        $equipmentLabelSql,
        $equipmentCommentsSql,
        $equipmentStatusIdSql,
        $equipmentColorIdSql,
        $colorSql,
        $labelSql,
        $notesSql
    )"
)) {
    idf_fail('DB error creating link: ' . mysqli_error($conn), 500);
}

idf_ok(['link_id' => (int)mysqli_insert_id($conn)]);
