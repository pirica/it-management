<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$equipmentId = (int)($data['equipment_id'] ?? 0);
if ($equipmentId <= 0) {
    idf_fail('Invalid equipment id');
}
$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}

$sql = "SELECT
            sp.id,
            sp.equipment_id,
            COALESCE(NULLIF(sp.hostname, ''), e.name) AS equipment_hostname,
            COALESCE(spt.type, sp.port_type) AS equipment_port_type,
            sp.port_number AS equipment_port,
            sp.vlan_id AS equipment_vlan_id,
            COALESCE(v.vlan_name, '') AS equipment_vlan_name,
            sp.{$switchPortLabelColumn} AS equipment_label,
            sp.comments AS equipment_comments,
            sp.status_id AS equipment_status_id,
            COALESCE(ss.status, '') AS equipment_status,
            sp.color_id AS equipment_color_id,
            COALESCE(NULLIF(sc.color_name, ''), sc.hex_color, '') AS equipment_color
        FROM switch_ports sp
        JOIN equipment e ON e.id = sp.equipment_id
        LEFT JOIN switch_port_types spt ON spt.id = sp.port_type AND spt.company_id = sp.company_id
        LEFT JOIN vlans v ON v.id = sp.vlan_id
        LEFT JOIN switch_status ss ON ss.id = sp.status_id
        LEFT JOIN cable_colors sc ON sc.id = sp.color_id
        WHERE sp.company_id = ?
          AND sp.equipment_id = ?
        ORDER BY COALESCE(spt.type, sp.port_type) ASC, sp.port_number ASC, sp.id ASC";

$stmt = mysqli_prepare($conn, $sql);
$res = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $equipmentId);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
    } else {
        idf_fail('DB error loading equipment ports: ' . mysqli_stmt_error($stmt), 500);
    }
    mysqli_stmt_close($stmt);
} else {
    idf_fail('DB error preparing statement: ' . mysqli_error($conn), 500);
}

$ports = [];
while ($row = mysqli_fetch_assoc($res)) {
    $ports[] = [
        'id' => (int)($row['id'] ?? 0),
        'equipment_id' => (int)($row['equipment_id'] ?? 0),
        'equipment_hostname' => (string)($row['equipment_hostname'] ?? ''),
        'equipment_port_type' => (string)($row['equipment_port_type'] ?? ''),
        'equipment_port' => (string)($row['equipment_port'] ?? ''),
        'equipment_vlan_id' => isset($row['equipment_vlan_id']) ? (int)$row['equipment_vlan_id'] : null,
        'equipment_vlan_name' => (string)($row['equipment_vlan_name'] ?? ''),
        'equipment_label' => (string)($row['equipment_label'] ?? ''),
        'equipment_comments' => (string)($row['equipment_comments'] ?? ''),
        'equipment_status_id' => isset($row['equipment_status_id']) ? (int)$row['equipment_status_id'] : null,
        'equipment_status' => (string)($row['equipment_status'] ?? ''),
        'equipment_color_id' => isset($row['equipment_color_id']) ? (int)$row['equipment_color_id'] : null,
        'equipment_color' => (string)($row['equipment_color'] ?? ''),
    ];
}

idf_ok(['ports' => $ports]);
