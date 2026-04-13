<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$port_id = (int)($data['port_id'] ?? 0);
if ($port_id <= 0) {
    idf_fail('Invalid port_id');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT pr.id, i.company_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id=?
     LIMIT 1"
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

$port_type_id = idf_resolve_port_type_id($conn, $company_id, $data['port_type_id'] ?? ($data['port_type'] ?? ''), 'RJ45');
$status_id = idf_resolve_status_id($conn, $company_id, $data['status_id'] ?? ($data['status'] ?? ''), 'Unknown');
if ($port_type_id <= 0) {
    idf_fail('Invalid port_type');
}

$label = trim((string)($data['label'] ?? ''));
$connected_to = trim((string)($data['connected_to'] ?? ''));
$vlan_id = idf_resolve_vlan_id($conn, $company_id, $data['vlan_id'] ?? ($data['vlan'] ?? ''));
$speed_id = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_fiber', 'name', $data['speed_id'] ?? ($data['speed'] ?? ''));
$poe_id = idf_resolve_named_lookup_id($conn, $company_id, 'equipment_poe', 'name', $data['poe_id'] ?? ($data['poe'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$cable_color_id = isset($data['cable_color_id']) ? (int)$data['cable_color_id'] : 0;

$label_val = $label !== '' ? $label : null;
$conn_val = $connected_to !== '' ? $connected_to : null;
$vlan_val = $vlan_id !== null ? (int)$vlan_id : 0;
$speed_val = $speed_id !== null ? (int)$speed_id : 0;
$poe_val = $poe_id !== null ? (int)$poe_id : 0;
$notes_val = $notes !== '' ? $notes : null;

$sql = "UPDATE idf_ports
        SET port_type=?,
            label=?,
            status_id=?,
            connected_to=?,
            vlan_id=NULLIF(?, 0),
            speed_id=NULLIF(?, 0),
            poe_id=NULLIF(?, 0),
            notes=?
        WHERE id=?
        LIMIT 1";

$stmtUpd = mysqli_prepare($conn, $sql);
if ($stmtUpd) {
    mysqli_stmt_bind_param($stmtUpd, 'isisiiisi', $port_type_id, $label_val, $status_id, $conn_val, $vlan_val, $speed_val, $poe_val, $notes_val, $port_id);
    if (!mysqli_stmt_execute($stmtUpd)) {
        idf_fail('DB error updating port: ' . mysqli_stmt_error($stmtUpd), 500);
    }
    mysqli_stmt_close($stmtUpd);
}

$stmtLinkUpdate = mysqli_prepare(
    $conn,
    "UPDATE idf_links
     SET cable_color_id = NULLIF(?, 0)
     WHERE port_id_a = ? OR port_id_b = ?"
);
if ($stmtLinkUpdate) {
    mysqli_stmt_bind_param($stmtLinkUpdate, 'iii', $cable_color_id, $port_id, $port_id);
    if (!mysqli_stmt_execute($stmtLinkUpdate)) {
        idf_fail('DB error updating link cable color: ' . mysqli_stmt_error($stmtLinkUpdate), 500);
    }
    mysqli_stmt_close($stmtLinkUpdate);
}

idf_ok();
