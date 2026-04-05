<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$equipmentId = (int)($data['equipment_id'] ?? 0);
if ($equipmentId <= 0) {
    idf_fail('Invalid equipment id');
}

$sql = "SELECT
            sp.id,
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
        WHERE sp.company_id = $company_id
          AND sp.equipment_id = $equipmentId
        ORDER BY sp.equipment_id ASC, sp.port_number ASC, sp.id ASC";

$res = mysqli_query($conn, $sql);
if (!$res) {
    idf_fail('DB error loading equipment ports: ' . mysqli_error($conn), 500);
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
        'equipment_label' => (string)($row['equipment_label'] ?? ''),
        'equipment_comments' => (string)($row['equipment_comments'] ?? ''),
        'equipment_status_id' => isset($row['equipment_status_id']) ? (int)$row['equipment_status_id'] : null,
        'equipment_color_id' => isset($row['equipment_color_id']) ? (int)$row['equipment_color_id'] : null,
    ];
}

idf_ok(['ports' => $ports]);
