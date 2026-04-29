<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$position_no = (int)($data['position_no'] ?? 0);
$position_id = isset($data['position_id']) ? (int)$data['position_id'] : 0;

$device_type_raw = trim((string)($data['device_type'] ?? ''));
$device_name = trim((string)($data['device_name'] ?? ''));
$equipment_id = isset($data['equipment_id']) ? (int)$data['equipment_id'] : 0;
$switch_rj45_id = isset($data['switch_rj45_id']) ? (int)$data['switch_rj45_id'] : 0;
$layout_id = isset($data['switch_port_numbering_layout_id']) ? (int)$data['switch_port_numbering_layout_id'] : 0;
$port_count = isset($data['port_count']) ? (int)$data['port_count'] : 0;
$notes = trim((string)($data['notes'] ?? ''));

if (!function_exists('idf_generate_unlinked_equipment_token')) {
    function idf_generate_unlinked_equipment_token(): string
    {
        return (string)random_int(1000, 9999) . '-' . (string)random_int(1000, 9999);
    }
}

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

if ($idf_id <= 0 || $position_no < 1 || $position_no > 100) {
    idf_fail('Invalid idf_id/position_no');
}
if ($device_name === '') {
    idf_fail('Device name is required');
}
if ($port_count < 0 || $port_count > 9999) {
    idf_fail('Invalid port_count');
}

$validTypeIdsByName = [];
$validTypeNamesById = [];
$stmtValidTypes = mysqli_prepare(
    $conn,
    "SELECT id, idfdevicetype_name
     FROM idf_device_type
     WHERE company_id=? AND active=1"
);
if ($stmtValidTypes) {
    mysqli_stmt_bind_param($stmtValidTypes, 'i', $company_id);
    mysqli_stmt_execute($stmtValidTypes);
    $resValidTypes = mysqli_stmt_get_result($stmtValidTypes);
    while ($resValidTypes && ($row = mysqli_fetch_assoc($resValidTypes))) {
        $typeId = (int)($row['id'] ?? 0);
        $typeName = strtolower(trim((string)($row['idfdevicetype_name'] ?? '')));
        if ($typeId > 0 && $typeName !== '') {
            $validTypeIdsByName[$typeName] = $typeId;
            $validTypeNamesById[$typeId] = $typeName;
        }
    }
    mysqli_stmt_close($stmtValidTypes);
}

if (!$validTypeIdsByName) {
    $validTypeIdsByName = [
        'switch' => 1,
        'patch_panel' => 2,
        'ups' => 3,
        'server' => 4,
        'other' => 5,
    ];
    $validTypeNamesById = array_flip($validTypeIdsByName);
}

$device_type_id = 0;
$device_type_name = '';
if ($device_type_raw !== '' && ctype_digit($device_type_raw)) {
    $candidateId = (int)$device_type_raw;
    if (isset($validTypeNamesById[$candidateId])) {
        $device_type_id = $candidateId;
        $device_type_name = (string)$validTypeNamesById[$candidateId];
    }
} else {
    $candidateName = strtolower($device_type_raw);
    if (isset($validTypeIdsByName[$candidateName])) {
        $device_type_id = (int)$validTypeIdsByName[$candidateName];
        $device_type_name = $candidateName;
    }
}

if ($device_type_id <= 0) {
    idf_fail('Invalid device_type');
}

if ($equipment_id > 0) {
    $stmtEquipment = mysqli_prepare(
        $conn,
        "SELECT e.name, e.notes, e.switch_rj45_id, e.switch_port_numbering_layout_id, er.name AS switch_rj45_name
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
        }
        if ($layout_id <= 0) {
            $layout_id = (int)($equipment['switch_port_numbering_layout_id'] ?? 0);
        }

        // Keep equipment table in sync
        $stmtUpdateEq = mysqli_prepare(
            $conn,
            "UPDATE equipment
             SET switch_rj45_id=?, switch_port_numbering_layout_id=?
             WHERE id=? AND company_id=?
             LIMIT 1"
        );
        if ($stmtUpdateEq) {
            mysqli_stmt_bind_param($stmtUpdateEq, 'iiii', $switch_rj45_id, $layout_id, $equipment_id, $company_id);
            mysqli_stmt_execute($stmtUpdateEq);
            mysqli_stmt_close($stmtUpdateEq);
        }
    }
}

if ($device_type_name === 'switch' && $switch_rj45_id <= 0) {
    idf_fail('RJ45 Ports are required for switch devices');
}

if ($equipment_id <= 0 && $device_type_name === 'switch' && $device_name !== '') {
    $stmtEquipmentByName = mysqli_prepare(
        $conn,
        "SELECT e.id, e.switch_rj45_id, e.switch_port_numbering_layout_id, er.name AS switch_rj45_name
         FROM equipment e
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         WHERE e.company_id = ? AND LOWER(e.name) = LOWER(?)
         ORDER BY e.id DESC
         LIMIT 1"
    );
    if ($stmtEquipmentByName) {
        mysqli_stmt_bind_param($stmtEquipmentByName, 'is', $company_id, $device_name);
        mysqli_stmt_execute($stmtEquipmentByName);
        $resEquipmentByName = mysqli_stmt_get_result($stmtEquipmentByName);
        $equipmentByName = $resEquipmentByName ? mysqli_fetch_assoc($resEquipmentByName) : null;
        mysqli_stmt_close($stmtEquipmentByName);
        if ($equipmentByName) {
            // Why: Linked equipment select may be left blank by UI flows; for switches we should still mirror known switch_ports by matching device_name.
            $equipment_id = (int)($equipmentByName['id'] ?? 0);
            if ($switch_rj45_id <= 0) {
                $switch_rj45_id = (int)($equipmentByName['switch_rj45_id'] ?? 0);
            }
            if ($layout_id <= 0) {
                $layout_id = (int)($equipmentByName['switch_port_numbering_layout_id'] ?? 0);
            }
            if ($port_count <= 0 && !empty($equipmentByName['switch_rj45_name']) && preg_match('/(\d+)/', (string)$equipmentByName['switch_rj45_name'], $matches)) {
                $port_count = (int)$matches[1];
            }
        }
    }
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

if ($device_name !== '') {
    $stmtDuplicateDeviceName = null;
    if ($position_id > 0) {
        $stmtDuplicateDeviceName = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND device_name=? AND id<>?
             LIMIT 1"
        );
        if ($stmtDuplicateDeviceName) {
            mysqli_stmt_bind_param($stmtDuplicateDeviceName, 'isi', $idf_id, $device_name, $position_id);
        }
    } else {
        $stmtDuplicateDeviceName = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND device_name=?
             LIMIT 1"
        );
        if ($stmtDuplicateDeviceName) {
            mysqli_stmt_bind_param($stmtDuplicateDeviceName, 'is', $idf_id, $device_name);
        }
    }

    if ($stmtDuplicateDeviceName) {
        mysqli_stmt_execute($stmtDuplicateDeviceName);
        $resDuplicateDeviceName = mysqli_stmt_get_result($stmtDuplicateDeviceName);
        $duplicateDeviceNameExists = $resDuplicateDeviceName && mysqli_num_rows($resDuplicateDeviceName) > 0;
        mysqli_stmt_close($stmtDuplicateDeviceName);

        if ($duplicateDeviceNameExists) {
            idf_fail('Equipment with the same device name is already on the list.');
        }
    }
}

if ($equipment_id > 0) {
    $equipmentIdString = (string)$equipment_id;
    $stmtDuplicateEquipment = null;
    if ($position_id > 0) {
        $stmtDuplicateEquipment = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND equipment_id=? AND id<>?
             LIMIT 1"
        );
        if ($stmtDuplicateEquipment) {
            mysqli_stmt_bind_param($stmtDuplicateEquipment, 'isi', $idf_id, $equipmentIdString, $position_id);
        }
    } else {
        $stmtDuplicateEquipment = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_positions
             WHERE idf_id=? AND equipment_id=?
             LIMIT 1"
        );
        if ($stmtDuplicateEquipment) {
            mysqli_stmt_bind_param($stmtDuplicateEquipment, 'is', $idf_id, $equipmentIdString);
        }
    }

    if ($stmtDuplicateEquipment) {
        mysqli_stmt_execute($stmtDuplicateEquipment);
        $resDuplicateEquipment = mysqli_stmt_get_result($stmtDuplicateEquipment);
        $duplicateEquipmentExists = $resDuplicateEquipment && mysqli_num_rows($resDuplicateEquipment) > 0;
        mysqli_stmt_close($stmtDuplicateEquipment);

        if ($duplicateEquipmentExists) {
            idf_fail('Equipment already on the list');
        }
    }
}

$notes_val = $notes !== '' ? $notes : null;
$equipmentId_val = $equipment_id > 0 ? (string)$equipment_id : idf_generate_unlinked_equipment_token();

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

    $layout_val = $layout_id > 0 ? $layout_id : null;
    $stmtUpdatePos = mysqli_prepare(
        $conn,
        "UPDATE idf_positions
         SET device_type=?,
             device_name=?,
             equipment_id=?,
             port_count=?,
             switch_port_numbering_layout_id=?,
             notes=?
         WHERE id=?
         LIMIT 1"
    );
    if ($stmtUpdatePos) {
        mysqli_stmt_bind_param($stmtUpdatePos, 'issiisi', $device_type_id, $device_name, $equipmentId_val, $port_count, $layout_val, $notes_val, $position_id);
        if (!mysqli_stmt_execute($stmtUpdatePos)) {
            idf_fail('DB error updating position: ' . mysqli_stmt_error($stmtUpdatePos), 500);
        }
        mysqli_stmt_close($stmtUpdatePos);
    }
} else {
    $layout_val = $layout_id > 0 ? $layout_id : null;
    $stmtInsertPos = mysqli_prepare(
        $conn,
        "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, port_count, switch_port_numbering_layout_id, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           company_id=VALUES(company_id),
           device_type=VALUES(device_type),
           device_name=VALUES(device_name),
           equipment_id=VALUES(equipment_id),
           port_count=VALUES(port_count),
           switch_port_numbering_layout_id=VALUES(switch_port_numbering_layout_id),
           notes=VALUES(notes)"
    );
    if ($stmtInsertPos) {
        mysqli_stmt_bind_param($stmtInsertPos, 'iiiissisi', $company_id, $idf_id, $position_no, $device_type_id, $device_name, $equipmentId_val, $port_count, $layout_val, $notes_val);
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
        $unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
        if ($unknownStatusId <= 0) {
            idf_fail('Unable to resolve default status for company', 500);
        }
        $defaultCableColorName = 'Gray';
        $defaultCableHexColor = '#808080';
        $portCableColorByNumber = [];
        $portTypeByNumber = [];
        if ($equipment_id > 0) {
            $stmtSwitchPortColors = mysqli_prepare(
                $conn,
                "SELECT sp.port_number, sp.port_type,
                        LOWER(TRIM(COALESCE(spt.type, CAST(sp.port_type AS CHAR)))) AS normalized_port_type,
                        cc.color_name,
                        cc.hex_color
                 FROM switch_ports sp
                 LEFT JOIN switch_port_types spt ON spt.id = sp.port_type AND spt.company_id = sp.company_id
                 LEFT JOIN cable_colors cc ON cc.id = sp.color_id AND cc.company_id = sp.company_id
                 WHERE sp.company_id = ? AND sp.equipment_id = ?"
            );
            if ($stmtSwitchPortColors) {
                mysqli_stmt_bind_param($stmtSwitchPortColors, 'ii', $company_id, $equipment_id);
                mysqli_stmt_execute($stmtSwitchPortColors);
                $resSwitchPortColors = mysqli_stmt_get_result($stmtSwitchPortColors);
                while ($resSwitchPortColors && ($switchPortColorRow = mysqli_fetch_assoc($resSwitchPortColors))) {
                    $normalizedPortType = trim((string)($switchPortColorRow['normalized_port_type'] ?? ''));
                    if ($normalizedPortType !== '' && $normalizedPortType !== 'rj45') {
                        // Why: IDF RJ45 auto-generation must not be contaminated by SFP rows that share the same port_number values.
                        continue;
                    }
                    $portNumber = (int)($switchPortColorRow['port_number'] ?? 0);
                    if ($portNumber <= 0) {
                        continue;
                    }
                    $rawPortType = $switchPortColorRow['port_type'] ?? '';
                    $normalizedPortType = trim((string)($switchPortColorRow['normalized_port_type'] ?? ''));
                    $resolvedPortTypeId = idf_resolve_port_type_id($conn, $company_id, $rawPortType, $normalizedPortType !== '' ? $normalizedPortType : 'RJ45');
                    if ($resolvedPortTypeId <= 0) {
                        continue;
                    }
                    $cableColorName = trim((string)($switchPortColorRow['color_name'] ?? ''));
                    $cableHexColor = strtoupper(trim((string)($switchPortColorRow['hex_color'] ?? '')));
                    if ($cableColorName === '') {
                        $cableColorName = $defaultCableColorName;
                    }
                    if (!preg_match('/^#[0-9A-F]{6}$/', $cableHexColor)) {
                        $cableHexColor = $defaultCableHexColor;
                    }
                    $portKey = $resolvedPortTypeId . ':' . $portNumber;
                    $portCableColorByNumber[$portKey] = [
                        'cable_color' => $cableColorName,
                        'hex_color' => $cableHexColor,
                    ];
                    $portTypeByNumber[$portKey] = [
                        'port_no' => $portNumber,
                        'port_type' => $resolvedPortTypeId,
                    ];
                }
                mysqli_stmt_close($stmtSwitchPortColors);
            }
        }
        $insertPortSql = "INSERT IGNORE INTO idf_ports (company_id, position_id, port_no, port_type, status_id, cable_color, hex_color) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtInsertPort = mysqli_prepare($conn, $insertPortSql);
        if ($stmtInsertPort) {
            if ($portTypeByNumber) {
                foreach ($portTypeByNumber as $portKey => $portMeta) {
                    $portCableColor = $portCableColorByNumber[$portKey]['cable_color'] ?? $defaultCableColorName;
                    $portHexColor = $portCableColorByNumber[$portKey]['hex_color'] ?? $defaultCableHexColor;
                    $portNo = (int)($portMeta['port_no'] ?? 0);
                    $portTypeId = (int)($portMeta['port_type'] ?? 0);
                    if ($portNo <= 0 || $portTypeId <= 0) {
                        continue;
                    }
                    mysqli_stmt_bind_param($stmtInsertPort, 'iiiiiss', $company_id, $pid, $portNo, $portTypeId, $unknownStatusId, $portCableColor, $portHexColor);
                    mysqli_stmt_execute($stmtInsertPort);
                }
            } else {
                $rj45PortTypeId = idf_resolve_port_type_id($conn, $company_id, 'RJ45', 'RJ45');
                if ($rj45PortTypeId <= 0) {
                    idf_fail('Unable to resolve default port type for company', 500);
                }
                for ($n = 1; $n <= $port_count; $n++) {
                    mysqli_stmt_bind_param($stmtInsertPort, 'iiiiiss', $company_id, $pid, $n, $rj45PortTypeId, $unknownStatusId, $defaultCableColorName, $defaultCableHexColor);
                    mysqli_stmt_execute($stmtInsertPort);
                }
            }
            mysqli_stmt_close($stmtInsertPort);
        }
    }
}

idf_ok();
