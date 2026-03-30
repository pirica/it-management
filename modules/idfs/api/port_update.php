<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$port_id = (int)($data['port_id'] ?? 0);
if ($port_id <= 0) {
    idf_fail('Invalid port_id');
}

$res = mysqli_query(
    $conn,
    "SELECT pr.id, i.company_id
     FROM idf_ports pr
     JOIN idf_positions p ON p.id=pr.position_id
     JOIN idfs i ON i.id=p.idf_id
     WHERE pr.id=$port_id
     LIMIT 1"
);
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row || (int)$row['company_id'] !== $company_id) {
    idf_fail('Not found', 404);
}

$port_type = (string)($data['port_type'] ?? 'RJ45');
$status = (string)($data['status'] ?? 'unknown');

$validType = ['RJ45', 'SFP', 'SFP+', 'LC', 'SC', 'OTHER'];
$validStatus = ['free', 'used', 'reserved', 'down', 'unknown'];

if (!in_array($port_type, $validType, true)) {
    idf_fail('Invalid port_type');
}
if (!in_array($status, $validStatus, true)) {
    idf_fail('Invalid status');
}

$label = trim((string)($data['label'] ?? ''));
$connected_to = trim((string)($data['connected_to'] ?? ''));
$vlan = trim((string)($data['vlan'] ?? ''));
$speed = trim((string)($data['speed'] ?? ''));
$poe = trim((string)($data['poe'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

$labelSql = $label !== '' ? ("'" . idf_escape($conn, $label) . "'") : 'NULL';
$connSql = $connected_to !== '' ? ("'" . idf_escape($conn, $connected_to) . "'") : 'NULL';
$vlanSql = $vlan !== '' ? ("'" . idf_escape($conn, $vlan) . "'") : 'NULL';
$speedSql = $speed !== '' ? ("'" . idf_escape($conn, $speed) . "'") : 'NULL';
$poeSql = $poe !== '' ? ("'" . idf_escape($conn, $poe) . "'") : 'NULL';
$notesSql = $notes !== '' ? ("'" . idf_escape($conn, $notes) . "'") : 'NULL';

$sql = "UPDATE idf_ports
        SET port_type='$port_type',
            label=$labelSql,
            status='$status',
            connected_to=$connSql,
            vlan=$vlanSql,
            speed=$speedSql,
            poe=$poeSql,
            notes=$notesSql
        WHERE id=$port_id
        LIMIT 1";

if (!mysqli_query($conn, $sql)) {
    idf_fail('DB error updating port: ' . mysqli_error($conn), 500);
}

idf_ok();
