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
$switch_rj45_id = isset($data['switch_rj45_id']) ? (int)$data['switch_rj45_id'] : 0;
$port_count = isset($data['port_count']) ? (int)$data['port_count'] : 0;
$notes = trim((string)($data['notes'] ?? ''));

if ($switch_rj45_id > 0) {
    $resSwitchRj45 = mysqli_query(
        $conn,
        "SELECT name
         FROM equipment_rj45
         WHERE id=$switch_rj45_id AND company_id=$company_id
         LIMIT 1"
    );
    $switchRj45 = $resSwitchRj45 ? mysqli_fetch_assoc($resSwitchRj45) : null;
    if (!$switchRj45) {
        idf_fail('Invalid port count option');
    }
    if (!empty($switchRj45['name']) && preg_match('/(\d+)/', (string)$switchRj45['name'], $matches)) {
        $port_count = (int)$matches[1];
    }
}

if ($idf_id <= 0 || $position_no < 1 || $position_no > 10) {
    idf_fail('Invalid idf_id/position_no');
}
if ($device_name === '') {
    idf_fail('Device name is required');
}
if ($port_count < 0 || $port_count > 9999) {
    idf_fail('Invalid port_count');
}

$validTypes = ['switch', 'patch_panel', 'ups', 'server', 'other'];
if (!in_array($device_type, $validTypes, true)) {
    idf_fail('Invalid device_type');
}

if ($equipment_id > 0) {
    $resEquipment = mysqli_query(
        $conn,
        "SELECT e.name, e.notes, e.switch_rj45_id, er.name AS switch_rj45_name
         FROM equipment e
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         WHERE e.id=$equipment_id AND e.company_id=$company_id
         LIMIT 1"
    );
    $equipment = $resEquipment ? mysqli_fetch_assoc($resEquipment) : null;
    if (!$equipment) {
        idf_fail('Selected equipment not found', 404);
    }

    $device_name = trim((string)($equipment['name'] ?? ''));
    $notes = trim((string)($equipment['notes'] ?? ''));

    if ($switch_rj45_id <= 0) {
        $switch_rj45_id = (int)($equipment['switch_rj45_id'] ?? 0);
        if (!empty($equipment['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipment['switch_rj45_name'], $matches)) {
            $port_count = (int)$matches[1];
        }
    } else {
        mysqli_query(
            $conn,
            "UPDATE equipment
             SET switch_rj45_id=$switch_rj45_id
             WHERE id=$equipment_id AND company_id=$company_id
             LIMIT 1"
        );
    }
}

if ($device_type === 'switch' && $switch_rj45_id <= 0) {
    idf_fail('RJ45 Ports are required for switch devices');
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
    $sql = "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, port_count, notes)
            VALUES ($company_id, $idf_id, $position_no, '$device_type', '$nameEsc', $equipSql, $port_count, $notesSql)
            ON DUPLICATE KEY UPDATE
              company_id=VALUES(company_id),
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
            $values[] = "($company_id,$pid,$n,'RJ45','unknown')";
        }
        mysqli_query($conn, 'INSERT IGNORE INTO idf_ports (company_id, position_id, port_no, port_type, status) VALUES ' . implode(',', $values));
    }
}

idf_ok();
