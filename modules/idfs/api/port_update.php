<?php
/**
 * IDF API - Update Port
 * 
 * Modifies the technical attributes of a single physical port on a patch panel.
 * Updates:
 * - Technical: Port type (RJ45, SFP, etc.), speed, PoE status, VLAN.
 * - Operational: Status (free, used, reserved, etc.), label, connected_to description.
 * - Administrative: Custom notes.
 * Validates the port ID against the company context before applying changes.
 */

require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$port_id = (int)($data['port_id'] ?? 0);
if ($port_id <= 0) {
    idf_fail('Invalid port_id');
}

// Ownership verification through the IDF hierarchy.
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

$port_type = (string)($data['port_type'] ?? 'RJ45');
$status = (string)($data['status'] ?? 'unknown');

// Restrict inputs to known valid sets to ensure database consistency.
$validType = ['RJ45', 'SFP', 'SFP+', 'LC', 'SC', 'OTHER'];
$validStatus = ['free', 'used', 'reserved', 'down', 'unknown'];

if (!in_array($port_type, $validType, true)) {
    idf_fail('Invalid port_type');
}
if (!in_array($status, $validStatus, true)) {
    idf_fail('Invalid status');
}

// Normalize empty strings to NULL for database storage.
$label = trim((string)($data['label'] ?? ''));
$connected_to = trim((string)($data['connected_to'] ?? ''));
$vlan = trim((string)($data['vlan'] ?? ''));
$speed = trim((string)($data['speed'] ?? ''));
$poe = trim((string)($data['poe'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

$label_val = $label !== '' ? $label : null;
$conn_val = $connected_to !== '' ? $connected_to : null;
$vlan_val = $vlan !== '' ? $vlan : null;
$speed_val = $speed !== '' ? $speed : null;
$poe_val = $poe !== '' ? $poe : null;
$notes_val = $notes !== '' ? $notes : null;

// Execute the update using prepared statements.
$sql = "UPDATE idf_ports
        SET port_type=?,
            label=?,
            status=?,
            connected_to=?,
            vlan=?,
            speed=?,
            poe=?,
            notes=?
        WHERE id=?
        LIMIT 1";

$stmtUpd = mysqli_prepare($conn, $sql);
if ($stmtUpd) {
    mysqli_stmt_bind_param($stmtUpd, 'ssssssssi', $port_type, $label_val, $status, $conn_val, $vlan_val, $speed_val, $poe_val, $notes_val, $port_id);
    if (!mysqli_stmt_execute($stmtUpd)) {
        idf_fail('DB error updating port: ' . mysqli_stmt_error($stmtUpd), 500);
    }
    mysqli_stmt_close($stmtUpd);
}

idf_ok();
