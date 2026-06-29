<?php
/**
 * Load a single switch_ports row in the same shape as switch_ports_by_equipment.php
 * so device.php can hydrate modals when we already know switch_ports.id from the port list query.
 */
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$switchPortId = (int)($data['switch_port_id'] ?? $data['id'] ?? 0);
if ($switchPortId <= 0) {
    idf_fail('Invalid switch_port id');
}

$switchPortLabelColumn = idf_first_existing_column($conn, 'switch_ports', ['to_patch_port', 'label', 'patch_port']);
if ($switchPortLabelColumn === null) {
    $switchPortLabelColumn = 'to_patch_port';
}

$sql = "SELECT
            sp.id,
            sp.equipment_id,
            COALESCE(e.name, '') AS equipment_name,
            COALESCE(NULLIF(e.hostname, ''), NULLIF(sp.hostname, ''), e.name) AS equipment_hostname,
            COALESCE(spt.type, sp.port_type) AS equipment_port_type,
            sp.port_number AS equipment_port,
            sp.vlan_id AS equipment_vlan_id,
            COALESCE(v.vlan_name, '') AS equipment_vlan_name,
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
            COALESCE(ss.status, '') AS equipment_status,
            sp.color_id AS equipment_color_id,
            COALESCE(NULLIF(sc.color_name, ''), sc.hex_color, '') AS equipment_color,
            COALESCE(sc.hex_color, '') AS equipment_color_hex
        FROM switch_ports sp
        JOIN equipment e ON e.id = sp.equipment_id AND e.company_id = sp.company_id AND e.deleted_at IS NULL
        LEFT JOIN switch_port_types spt ON spt.id = sp.port_type AND spt.company_id = sp.company_id
        LEFT JOIN vlans v ON v.id = sp.vlan_id AND v.company_id = sp.company_id
        LEFT JOIN switch_status ss ON ss.id = sp.status_id AND ss.company_id = sp.company_id
        LEFT JOIN cable_colors sc ON sc.id = sp.color_id AND sc.company_id = sp.company_id
        WHERE sp.company_id = ?
          AND sp.id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    idf_fail('DB error preparing statement: ' . mysqli_error($conn), 500);
}

mysqli_stmt_bind_param($stmt, 'ii', $company_id, $switchPortId);
if (!mysqli_stmt_execute($stmt)) {
    $stmtError = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    idf_fail('DB error loading switch port: ' . $stmtError, 500);
}

$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    idf_fail('Switch port not found', 404);
}

$port = [
    'id' => (int)($row['id'] ?? 0),
    'equipment_id' => (int)($row['equipment_id'] ?? 0),
    'equipment_name' => (string)($row['equipment_name'] ?? ''),
    'equipment_hostname' => (string)($row['equipment_hostname'] ?? ''),
    'equipment_port_type' => (string)($row['equipment_port_type'] ?? ''),
    'equipment_port' => (string)($row['equipment_port'] ?? ''),
    'equipment_vlan_id' => isset($row['equipment_vlan_id']) ? (int)$row['equipment_vlan_id'] : null,
    'equipment_vlan_name' => (string)($row['equipment_vlan_name'] ?? ''),
    'equipment_rj45_speed_id' => isset($row['equipment_rj45_speed_id']) ? (int)$row['equipment_rj45_speed_id'] : null,
    'equipment_fiber_port_id' => isset($row['equipment_fiber_port_id']) ? (int)$row['equipment_fiber_port_id'] : null,
    'equipment_fiber_patch_id' => isset($row['equipment_fiber_patch_id']) ? (int)$row['equipment_fiber_patch_id'] : null,
    'equipment_fiber_rack_id' => isset($row['equipment_fiber_rack_id']) ? (int)$row['equipment_fiber_rack_id'] : null,
    'equipment_to_idf_id' => isset($row['equipment_to_idf_id']) ? (int)$row['equipment_to_idf_id'] : null,
    'equipment_to_rack_id' => isset($row['equipment_to_rack_id']) ? (int)$row['equipment_to_rack_id'] : null,
    'equipment_to_location_id' => isset($row['equipment_to_location_id']) ? (int)$row['equipment_to_location_id'] : null,
    'equipment_label' => (string)($row['equipment_label'] ?? ''),
    'equipment_comments' => (string)($row['equipment_comments'] ?? ''),
    'equipment_status_id' => isset($row['equipment_status_id']) ? (int)$row['equipment_status_id'] : null,
    'equipment_status' => (string)($row['equipment_status'] ?? ''),
    'equipment_color_id' => isset($row['equipment_color_id']) ? (int)$row['equipment_color_id'] : null,
    'equipment_color' => (string)($row['equipment_color'] ?? ''),
    'equipment_color_hex' => (string)($row['equipment_color_hex'] ?? ''),
];

idf_ok(['port' => $port]);
