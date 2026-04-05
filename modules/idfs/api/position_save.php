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

if ($equipment_id <= 0) {
    $equipmentColumns = [];
    $resEquipmentDescribe = mysqli_query($conn, "DESCRIBE equipment");
    while ($resEquipmentDescribe && ($column = mysqli_fetch_assoc($resEquipmentDescribe))) {
        $equipmentColumns[(string)$column['Field']] = $column;
    }
    if (!$equipmentColumns || !isset($equipmentColumns['name'])) {
        idf_fail('Equipment table is missing required structure', 500);
    }

    $generatedEquipmentToken = random_int(1000, 9999);
    $generatedEquipmentName = $device_name !== ''
        ? $device_name . ' #' . $generatedEquipmentToken
        : 'IDF Device #' . $generatedEquipmentToken;

    $insertColumns = ['name'];
    $insertTypes = 's';
    $insertValues = [$generatedEquipmentName];

    if (isset($equipmentColumns['company_id'])) {
        $insertColumns[] = 'company_id';
        $insertTypes .= 'i';
        $insertValues[] = $company_id;
    }
    if (isset($equipmentColumns['active'])) {
        $insertColumns[] = 'active';
        $insertTypes .= 'i';
        $insertValues[] = 1;
    }
    if (isset($equipmentColumns['switch_rj45_id']) && $switch_rj45_id > 0) {
        $insertColumns[] = 'switch_rj45_id';
        $insertTypes .= 'i';
        $insertValues[] = $switch_rj45_id;
    }
    if (isset($equipmentColumns['notes']) && $notes !== '') {
        $insertColumns[] = 'notes';
        $insertTypes .= 's';
        $insertValues[] = $notes;
    }

    if (isset($equipmentColumns['equipment_type_id'])) {
        $fallbackEquipmentTypeId = 0;
        $stmtFallbackType = mysqli_prepare(
            $conn,
            "SELECT id
             FROM equipment_types
             WHERE company_id=?
             ORDER BY CASE WHEN LOWER(TRIM(name))='other' THEN 0 ELSE 1 END, id ASC
             LIMIT 1"
        );
        if ($stmtFallbackType) {
            mysqli_stmt_bind_param($stmtFallbackType, 'i', $company_id);
            mysqli_stmt_execute($stmtFallbackType);
            $resFallbackType = mysqli_stmt_get_result($stmtFallbackType);
            $rowFallbackType = $resFallbackType ? mysqli_fetch_assoc($resFallbackType) : null;
            $fallbackEquipmentTypeId = $rowFallbackType ? (int)$rowFallbackType['id'] : 0;
            mysqli_stmt_close($stmtFallbackType);
        }
        if ($fallbackEquipmentTypeId > 0) {
            $insertColumns[] = 'equipment_type_id';
            $insertTypes .= 'i';
            $insertValues[] = $fallbackEquipmentTypeId;
        }
    }

    if (isset($equipmentColumns['status_id'])) {
        $fallbackStatusId = 0;
        $stmtFallbackStatus = mysqli_prepare(
            $conn,
            "SELECT id
             FROM equipment_statuses
             WHERE company_id=?
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtFallbackStatus) {
            mysqli_stmt_bind_param($stmtFallbackStatus, 'i', $company_id);
            mysqli_stmt_execute($stmtFallbackStatus);
            $resFallbackStatus = mysqli_stmt_get_result($stmtFallbackStatus);
            $rowFallbackStatus = $resFallbackStatus ? mysqli_fetch_assoc($resFallbackStatus) : null;
            $fallbackStatusId = $rowFallbackStatus ? (int)$rowFallbackStatus['id'] : 0;
            mysqli_stmt_close($stmtFallbackStatus);
        }
        if ($fallbackStatusId > 0) {
            $insertColumns[] = 'status_id';
            $insertTypes .= 'i';
            $insertValues[] = $fallbackStatusId;
        }
    }

    foreach ($equipmentColumns as $field => $meta) {
        $isAutoIncrement = stripos((string)$meta['Extra'], 'auto_increment') !== false;
        $hasDefault = $meta['Default'] !== null;
        $isNullable = strtoupper((string)$meta['Null']) === 'YES';
        if ($isAutoIncrement || $hasDefault || $isNullable) {
            continue;
        }
        if (in_array($field, $insertColumns, true)) {
            continue;
        }
        idf_fail('Cannot auto-create equipment because required field "' . $field . '" is missing', 422);
    }

    $escapedColumns = array_map(static fn($column) => '`' . $column . '`', $insertColumns);
    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $insertSql = 'INSERT INTO equipment (' . implode(', ', $escapedColumns) . ') VALUES (' . $placeholders . ')';
    $stmtCreateEquipment = mysqli_prepare($conn, $insertSql);
    if (!$stmtCreateEquipment) {
        idf_fail('DB error creating equipment placeholder', 500);
    }
    mysqli_stmt_bind_param($stmtCreateEquipment, $insertTypes, ...$insertValues);
    if (!mysqli_stmt_execute($stmtCreateEquipment)) {
        idf_fail('DB error creating equipment placeholder: ' . mysqli_stmt_error($stmtCreateEquipment), 500);
    }
    $equipment_id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmtCreateEquipment);
    if ($equipment_id <= 0) {
        idf_fail('Failed to create equipment placeholder', 500);
    }
}

$notes_val = $notes !== '' ? $notes : null;
$equipmentId_val = $equipment_id > 0 ? $equipment_id : null;

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
