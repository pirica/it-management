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
    $stmtSwitchRj45 = mysqli_prepare(
        $conn,
        "SELECT name
         FROM equipment_rj45
         WHERE id=? AND company_id=?
         LIMIT 1"
    );
    if ($stmtSwitchRj45) {
        mysqli_stmt_bind_param($stmtSwitchRj45, 'ii', $switch_rj45_id, $company_id);
        mysqli_stmt_execute($stmtSwitchRj45);
        $resSwitchRj45 = mysqli_stmt_get_result($stmtSwitchRj45);
        $switchRj45 = $resSwitchRj45 ? mysqli_fetch_assoc($resSwitchRj45) : null;
        mysqli_stmt_close($stmtSwitchRj45);

        if (!$switchRj45) {
            idf_fail('Invalid port count option');
        }
        if (!empty($switchRj45['name']) && preg_match('/(\d+)/', (string)$switchRj45['name'], $matches)) {
            $port_count = (int)$matches[1];
        }
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
    $stmtEquipment = mysqli_prepare(
        $conn,
        "SELECT e.name, e.notes, e.switch_rj45_id, er.name AS switch_rj45_name
         FROM equipment e
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         WHERE e.id=? AND e.company_id=?
         LIMIT 1"
    );
    if ($stmtEquipment) {
        mysqli_stmt_bind_param($stmtEquipment, 'ii', $equipment_id, $company_id);
        mysqli_stmt_execute($stmtEquipment);
        $resEquipment = mysqli_stmt_get_result($stmtEquipment);
        $equipment = $resEquipment ? mysqli_fetch_assoc($resEquipment) : null;
        mysqli_stmt_close($stmtEquipment);

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
            $stmtUpdateEq = mysqli_prepare(
                $conn,
                "UPDATE equipment
                 SET switch_rj45_id=?
                 WHERE id=? AND company_id=?
                 LIMIT 1"
            );
            if ($stmtUpdateEq) {
                mysqli_stmt_bind_param($stmtUpdateEq, 'iii', $switch_rj45_id, $equipment_id, $company_id);
                mysqli_stmt_execute($stmtUpdateEq);
                mysqli_stmt_close($stmtUpdateEq);
            }
        }
    }
}

if ($device_type === 'switch' && $switch_rj45_id <= 0) {
    idf_fail('RJ45 Ports are required for switch devices');
}

$stmtIdf = mysqli_prepare($conn, "SELECT id FROM idfs WHERE id=? AND company_id=? LIMIT 1");
if ($stmtIdf) {
    mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
    mysqli_stmt_execute($stmtIdf);
    $resIdf = mysqli_stmt_get_result($stmtIdf);
    $foundIdf = $resIdf && mysqli_num_rows($resIdf) === 1;
    mysqli_stmt_close($stmtIdf);

    if (!$foundIdf) {
        idf_fail('IDF not found', 404);
    }
}

$notes_val = $notes !== '' ? $notes : null;
$equipmentId_val = $equipment_id > 0 ? $equipment_id : random_int(1000, 9999);

if ($position_id > 0) {
    $stmtPos = mysqli_prepare(
        $conn,
        "SELECT p.id
         FROM idf_positions p
         JOIN idfs i ON i.id=p.idf_id
         WHERE p.id=? AND i.company_id=?
         LIMIT 1"
    );
    if ($stmtPos) {
        mysqli_stmt_bind_param($stmtPos, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmtPos);
        $resPos = mysqli_stmt_get_result($stmtPos);
        $foundPos = $resPos && mysqli_num_rows($resPos) === 1;
        mysqli_stmt_close($stmtPos);

        if (!$foundPos) {
            idf_fail('Position not found', 404);
        }
    }

    $stmtUpdatePos = mysqli_prepare(
        $conn,
        "UPDATE idf_positions
         SET device_type=?,
             device_name=?,
             equipment_id=?,
             port_count=?,
             notes=?
         WHERE id=?
         LIMIT 1"
    );
    if ($stmtUpdatePos) {
        mysqli_stmt_bind_param($stmtUpdatePos, 'ssiisi', $device_type, $device_name, $equipmentId_val, $port_count, $notes_val, $position_id);
        if (!mysqli_stmt_execute($stmtUpdatePos)) {
            idf_fail('DB error updating position: ' . mysqli_stmt_error($stmtUpdatePos), 500);
        }
        mysqli_stmt_close($stmtUpdatePos);
    }
} else {
    $stmtInsertPos = mysqli_prepare(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, port_count, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           company_id=VALUES(company_id),
           device_type=VALUES(device_type),
           device_name=VALUES(device_name),
           equipment_id=VALUES(equipment_id),
           port_count=VALUES(port_count),
           notes=VALUES(notes)"
    );
    if ($stmtInsertPos) {
        mysqli_stmt_bind_param($stmtInsertPos, 'iiisssis', $company_id, $idf_id, $position_no, $device_type, $device_name, $equipmentId_val, $port_count, $notes_val);
        if (!mysqli_stmt_execute($stmtInsertPos)) {
            idf_fail('DB error saving position: ' . mysqli_stmt_error($stmtInsertPos), 500);
        }
        mysqli_stmt_close($stmtInsertPos);
    }
}

$stmtPid = mysqli_prepare($conn, "SELECT id FROM idf_positions WHERE idf_id=? AND position_no=? LIMIT 1");
if ($stmtPid) {
    mysqli_stmt_bind_param($stmtPid, 'ii', $idf_id, $position_no);
    mysqli_stmt_execute($stmtPid);
    $resPid = mysqli_stmt_get_result($stmtPid);
    $pidRow = $resPid ? mysqli_fetch_assoc($resPid) : null;
    $pid = $pidRow ? (int)$pidRow['id'] : 0;
    mysqli_stmt_close($stmtPid);
}

if ($pid > 0) {
    $stmtCnt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM idf_ports WHERE position_id=?");
    $existing = 0;
    if ($stmtCnt) {
        mysqli_stmt_bind_param($stmtCnt, 'i', $pid);
        mysqli_stmt_execute($stmtCnt);
        $resCnt = mysqli_stmt_get_result($stmtCnt);
        if ($resCnt && ($r = mysqli_fetch_assoc($resCnt))) {
            $existing = (int)$r['c'];
        }
        mysqli_stmt_close($stmtCnt);
    }

    if ($port_count > 0 && $existing === 0) {
        $insertPortSql = "INSERT IGNORE INTO idf_ports (company_id, position_id, port_no, port_type, status) VALUES (?, ?, ?, 'RJ45', 'unknown')";
        $stmtInsertPort = mysqli_prepare($conn, $insertPortSql);
        if ($stmtInsertPort) {
            for ($n = 1; $n <= $port_count; $n++) {
                mysqli_stmt_bind_param($stmtInsertPort, 'iii', $company_id, $pid, $n);
                mysqli_stmt_execute($stmtInsertPort);
            }
            mysqli_stmt_close($stmtInsertPort);
        }
    }
}

idf_ok();
