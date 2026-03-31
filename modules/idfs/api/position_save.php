<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);
$position_id = isset($data['position_id']) ? (int)$data['position_id'] : 0;

$device_type = (string)($data['device_type'] ?? 'other');
$device_name = trim((string)($data['device_name'] ?? ''));
$equipment_id = isset($data['equipment_id']) ? (int)$data['equipment_id'] : 0;
$port_count = (int)($data['port_count'] ?? 0);
$notes = trim((string)($data['notes'] ?? ''));

if ($equipment_id > 0) {
    $resEquipment = mysqli_query(
        $conn,
        "SELECT name, notes, switch_rj45_id
         FROM equipment
         WHERE id=$equipment_id AND company_id=$company_id
         LIMIT 1"
    );
    if (!$resEquipment || mysqli_num_rows($resEquipment) !== 1) {
        idf_fail('Equipment not found', 404);
    }
    $equipmentRow = mysqli_fetch_assoc($resEquipment) ?: [];
    $device_name = trim((string)($equipmentRow['name'] ?? ''));
    $port_count = (int)($equipmentRow['switch_rj45_id'] ?? 0);
    $notes = trim((string)($equipmentRow['notes'] ?? ''));
}

if ($idf_id <= 0 || $position_no < 1 || $position_no > 10) {
    idf_fail('Invalid idf_id/position_no');
}
if ($device_name === '') {
    idf_fail('Device name is required');
}
if ($port_count < 0 || $port_count > 128) {
    idf_fail('Invalid port_count');
}

$validTypes = ['switch', 'patch_panel', 'ups', 'server', 'other'];
if (!in_array($device_type, $validTypes, true)) {
    idf_fail('Invalid device_type');
}

$resIdf = mysqli_query($conn, "SELECT id FROM idfs WHERE id=$idf_id AND company_id=$company_id LIMIT 1");
if (!$resIdf || mysqli_num_rows($resIdf) !== 1) {
    idf_fail('IDF not found', 404);
}

$nameEsc = idf_escape($conn, $device_name);
$notesSql = $notes !== '' ? ("'" . idf_escape($conn, $notes) . "'") : 'NULL';
$equipSql = $equipment_id > 0 ? (string)$equipment_id : 'NULL';

if ($position_id > 0) {
    $resPos = mysqli_query(
        $conn,
        "SELECT p.id
         FROM idf_positions p
         JOIN idfs i ON i.id=p.idf_id
         WHERE p.id=$position_id AND i.company_id=$company_id
         LIMIT 1"
    );
    if (!$resPos || mysqli_num_rows($resPos) !== 1) {
        idf_fail('Position not found', 404);
    }

    $sql = "UPDATE idf_positions
            SET device_type='$device_type',
                device_name='$nameEsc',
                equipment_id=$equipSql,
                port_count=$port_count,
                notes=$notesSql
            WHERE id=$position_id
            LIMIT 1";
    if (!mysqli_query($conn, $sql)) {
        idf_fail('DB error updating position: ' . mysqli_error($conn), 500);
    }
} else {
    $sql = "INSERT INTO idf_positions (idf_id, position_no, device_type, device_name, equipment_id, port_count, notes)
            VALUES ($idf_id, $position_no, '$device_type', '$nameEsc', $equipSql, $port_count, $notesSql)
            ON DUPLICATE KEY UPDATE
              device_type=VALUES(device_type),
              device_name=VALUES(device_name),
              equipment_id=VALUES(equipment_id),
              port_count=VALUES(port_count),
              notes=VALUES(notes)";
    if (!mysqli_query($conn, $sql)) {
        idf_fail('DB error saving position: ' . mysqli_error($conn), 500);
    }
}

$resPid = mysqli_query($conn, "SELECT id FROM idf_positions WHERE idf_id=$idf_id AND position_no=$position_no LIMIT 1");
$pidRow = $resPid ? mysqli_fetch_assoc($resPid) : null;
$pid = $pidRow ? (int)$pidRow['id'] : 0;

if ($pid > 0) {
    $resCnt = mysqli_query($conn, "SELECT COUNT(*) AS c FROM idf_ports WHERE position_id=$pid");
    $existing = 0;
    if ($resCnt && ($r = mysqli_fetch_assoc($resCnt))) {
        $existing = (int)$r['c'];
    }

    if ($port_count > 0 && $existing === 0) {
        $values = [];
        for ($n = 1; $n <= $port_count; $n++) {
            $values[] = "($pid,$n,'RJ45','unknown')";
        }
        mysqli_query($conn, 'INSERT IGNORE INTO idf_ports (position_id, port_no, port_type, status) VALUES ' . implode(',', $values));
    }
}

idf_ok();
